<?php
require_once __DIR__ . '/includes/helpers.php';
if (current_user()) {
    clear_active_user_session_if_match((int)current_user()['id'], (string)current_user()['role'], session_id());
}
if (current_user() && current_user()['role'] === 'admin') {
	log_admin_activity('logout', 'Admin logged out', current_user(), 'logout.php');
}
session_destroy();
redirect(url('index.php'));
