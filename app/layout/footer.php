  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $jsVer = (string)(@filemtime(__DIR__ . '/../../public/assets/js/app.js') ?: time()); ?>
<script>window.EDUTRACK = { baseUrl: "<?= BASE_URL ?>" , csrf: "<?= csrf_token() ?>" };</script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= urlencode($jsVer) ?>"></script>
</body>
</html>
