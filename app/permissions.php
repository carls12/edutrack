<?php
declare(strict_types=1);

const PAGE_ROLES = [
  'home' => ['admin','principal','teacher','prefect'],
  'dashboard' => ['admin','principal','teacher'],
  'admin_dashboard' => ['admin'],
  'attendance' => ['admin','principal','prefect'],
  'attendance_management' => ['admin','principal'],
  'timetable' => ['admin','principal','teacher','prefect'],
  'reports' => ['admin','principal','teacher'],
  'settings' => ['admin'],
  'branding' => ['admin'],
  'password_reset' => ['admin'],

  // Admin management
  'admin_users' => ['admin'],
  'admin_teachers' => ['admin','principal'],
  'teacher_stamps' => ['admin','principal'],
  'admin_classes' => ['admin'],
  'admin_subjects' => ['admin'],
  'admin_assignments' => ['admin'],
  'admin_availability' => ['admin'],

  // Teacher portal
  'teacher_portal' => ['teacher'],
];
