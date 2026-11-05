<?php
/** Shared code for the Customer Portal staff pages (Portal Accounts, Portal Requests, Portal Notices). Tables: customer_portal/schema.sql. */
require_once __DIR__ . '/customer_common.php';

const PORTAL_URL = '../../customer_portal/login.php';

function portal_ready(mysqli $db)
{
    return cm_ready($db) && sales_tables_ready($db, ['portal_accounts', 'portal_login_log', 'portal_requests', 'portal_request_messages', 'portal_notices', 'portal_settings']);
}

/** Page start for the portal pages: Customer Management checks plus the portal install notice. */
function portal_page_start(mysqli $db, $title, $active)
{
    cm_page_start($db, $title, $active);
    if (!portal_ready($db)) {
        echo '<div class="errorbox"><strong>The Customer Portal is not installed.</strong><br>Import <code>customer_portal/schema.sql</code> (after <code>billing/customer/schema.sql</code>), then reload this page.</div>';
        mgmt_page_end();
        exit;
    }
}
