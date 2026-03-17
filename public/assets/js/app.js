// Sidebar toggle
const sidebar = document.querySelector(".sidebar");
const toggleBtn = document.getElementById("sidebarToggle");
if (toggleBtn && sidebar) toggleBtn.addEventListener("click", () => sidebar.classList.toggle("open"));

// Mobile UX: close the sidebar after selecting a navigation item.
document.addEventListener("click", (e) => {
  if (!sidebar) return;
  const navLink = e.target.closest(".sidebar .nav-link");
  if (!navLink) return;
  if (window.innerWidth <= 991) sidebar.classList.remove("open");
});

const OFFLINE_QUEUE_KEY = "edutrack_offline_queue_v1";

function getOfflineQueue(){
  try{
    const raw = localStorage.getItem(OFFLINE_QUEUE_KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr : [];
  }catch(_){
    return [];
  }
}
function setOfflineQueue(queue){
  localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
  renderOfflineQueueInfo();
}
function enqueueOfflineRequest(req){
  const queue = getOfflineQueue();
  queue.push(req);
  setOfflineQueue(queue);
}
function isAttendanceRecordEndpoint(url){
  return /attendance_(record|card_action)\.php(\?|$)/.test(String(url || ""));
}
function renderOfflineQueueInfo(){
  const el = document.getElementById("offlineQueueInfo");
  if(!el) return;
  const count = getOfflineQueue().length;
  if(count <= 0){
    el.classList.add("d-none");
    el.textContent = "";
    return;
  }
  el.classList.remove("d-none");
  el.textContent = `${count} attendance record(s) waiting for sync.`;
}
async function syncOfflineQueue(){
  if(!navigator.onLine) return;
  let queue = getOfflineQueue();
  if(queue.length === 0) return;

  const remaining = [];
  for(const req of queue){
    try{
      await api(req.url, req.options || {});
    }catch(err){
      remaining.push(req);
      const msg = String(err?.message || "");
      if(!/network|fetch|offline/i.test(msg)) {
        // Validation/business error: drop invalid entry so queue can progress.
        continue;
      }
    }
  }
  setOfflineQueue(remaining);
  if(queue.length > 0 && remaining.length === 0){
    toast("Offline attendance synced", "success");
  }
}

// Connection indicator
function updateNetUI(){
  const el = document.getElementById("netStatus");
  if(!el) return;
  const dot = el.querySelector(".status-dot");
  const text = el.querySelector("span:last-child");
  const online = navigator.onLine;

  dot.style.background = online ? "#22c55e" : "#f97316";
  dot.style.boxShadow = online ? "0 0 0 4px rgba(34,197,94,.15)" : "0 0 0 4px rgba(249,115,22,.15)";
  text.textContent = online ? "Online" : "Offline";
}
window.addEventListener("online", updateNetUI);
window.addEventListener("offline", updateNetUI);
window.addEventListener("online", syncOfflineQueue);
updateNetUI();

// Helper: JSON fetch (CSRF + session cookies)
async function api(url, opts = {}){
  const headers = Object.assign({"Content-Type":"application/json"}, (opts.headers||{}));
  headers["X-CSRF"] = window.EDUTRACK?.csrf || "";

  const res = await fetch(url, Object.assign({}, opts, {
    headers,
    credentials: "same-origin" // IMPORTANT for PHP session cookie
  }));

  const ct = res.headers.get("content-type") || "";
  const raw = await res.text();
  let data;

  if(ct.includes("application/json")){
    try{
      data = raw ? JSON.parse(raw) : {};
    }catch(_){
      const preview = raw.replace(/\s+/g, " ").trim().slice(0, 180);
      throw new Error(preview || "Invalid JSON response from server.");
    }
  }else{
    data = { ok:false, error: raw };
  }

  if(!res.ok || data.ok === false){
    const msg = data.error || ("HTTP " + res.status);
    throw new Error(msg);
  }
  return data;
}

// Toast (dark theme)
function toast(msg, type="info"){
  const el = document.createElement("div");
  const bg =
    type === "error" ? "text-bg-danger" :
    type === "success" ? "text-bg-success" :
    "text-bg-dark";

  el.className = "toast align-items-center "+bg+" border-0 position-fixed bottom-0 end-0 m-3 show";
  el.role = "alert";
  el.innerHTML = `
    <div class="d-flex">
      <div class="toast-body">${msg}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"></button>
    </div>`;
  document.body.appendChild(el);
  el.querySelector("button").onclick = ()=> el.remove();
  setTimeout(()=> el.remove(), 3500);
}

// Generic modal form submit (expects form with data-api endpoint + JSON body)
document.addEventListener("submit", async (e)=>{
  const form = e.target;
  if(!(form instanceof HTMLFormElement)) return;
  if(!form.dataset.api) return;
  e.preventDefault();

  const payload = Object.fromEntries(new FormData(form).entries());
  // normalize checkbox
  form.querySelectorAll("input[type=checkbox]").forEach(cb=>{
    if(cb.name) payload[cb.name] = cb.checked ? "1" : "0";
  });

  const endpoint = form.dataset.api;
  const requestOptions = { method:"POST", body: JSON.stringify(payload) };

  if(isAttendanceRecordEndpoint(endpoint) && !navigator.onLine){
    enqueueOfflineRequest({ url: endpoint, options: requestOptions, createdAt: Date.now() });
    toast("Saved offline. Will sync when online.", "info");
    form.reset();
    return;
  }

  try{
    const out = await api(endpoint, requestOptions);
    toast("Saved successfully", "success");
    if(form.dataset.onSuccess) {
      window[form.dataset.onSuccess]?.(out);
    } else {
      window.location.reload();
    }
  }catch(err){
    const msg = String(err?.message || "");
    if (isAttendanceRecordEndpoint(endpoint) && /network|fetch|offline/i.test(msg)) {
      enqueueOfflineRequest({ url: endpoint, options: requestOptions, createdAt: Date.now() });
      toast("Network issue. Attendance queued for sync.", "info");
      return;
    }
    toast("Error: " + err.message, "error");
  }
});

// Button actions
document.addEventListener("click", async (e)=>{
  const btn = e.target.closest("[data-action]");
  if(!btn) return;
  const action = btn.dataset.action;
  const endpoint = btn.dataset.api;
  const id = btn.dataset.id;

  try{
    if(action === "delete"){
      if(!confirm("Delete this item?")) return;
      await api(endpoint, { method:"POST", body: JSON.stringify({ id }) });
      toast("Deleted", "success");
      window.location.reload();
    }
    if(action === "validate"){
      await api(endpoint, { method:"POST", body: JSON.stringify({ id }) });
      toast("Validated", "success");
      window.location.reload();
    }
    if(action === "generate_timetable"){
      await api(endpoint, { method:"POST", body: JSON.stringify({}) });
      toast("Timetable generated", "success");
      window.location.reload();
    }
    if(action === "attendance_card_action"){
      const mode = btn.dataset.mode;
      const timetableEntryId = btn.dataset.timetableEntryId;
      let reason = "";
      if(mode === "absent"){
        reason = (prompt("Absence reason (e.g. Sick, Personal, Official):") || "").trim();
        if(reason === "") return;
      }
      const payload = { action: mode, timetable_entry_id: timetableEntryId, reason };
      const requestOptions = { method: "POST", body: JSON.stringify(payload) };

      if(!navigator.onLine){
        enqueueOfflineRequest({ url: endpoint, options: requestOptions, createdAt: Date.now() });
        toast("Saved offline. Will sync when online.", "info");
        return;
      }

      try{
        await api(endpoint, requestOptions);
        toast("Attendance saved", "success");
        window.location.reload();
      }catch(err){
        const msg = String(err?.message || "");
        if (/network|fetch|offline/i.test(msg)) {
          enqueueOfflineRequest({ url: endpoint, options: requestOptions, createdAt: Date.now() });
          toast("Network issue. Attendance queued for sync.", "info");
          return;
        }
        throw err;
      }
    }
  }catch(err){
    toast("Error: " + err.message, "error");
  }
});

renderOfflineQueueInfo();
syncOfflineQueue();
