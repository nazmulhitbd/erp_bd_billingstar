<?php
mysqli_report(MYSQLI_REPORT_OFF);

function busbook_mysql_connection($connection = null)
{
    if ($connection instanceof mysqli) {
        return $connection;
    }

    return isset($GLOBALS['link']) && $GLOBALS['link'] instanceof mysqli ? $GLOBALS['link'] : null;
}

function mysql_connect($server = 'localhost', $username = '', #[\SensitiveParameter] $password = '', $newLink = false, $clientFlags = 0)
{
    $GLOBALS['_busbook_mysql_connect_errno'] = 0;
    $GLOBALS['_busbook_mysql_connect_error'] = '';
    $host = (string) $server;
    $port = (int) ini_get('mysqli.default_port');

    if (preg_match('/^(.+):([0-9]+)$/', $host, $matches)) {
        $host = trim($matches[1], '[]');
        $port = (int) $matches[2];
        // A configured port selects TCP; mysqli otherwise treats localhost as
        // a Unix socket and ignores the port.
        if (strtolower($host) === 'localhost') {
            $host = '127.0.0.1';
        }
    }

    $connection = mysqli_init();
    if (!$connection || !@mysqli_real_connect($connection, $host, (string) $username, (string) $password, null, $port, null, (int) $clientFlags)) {
        $GLOBALS['_busbook_mysql_connect_errno'] = mysqli_connect_errno();
        $GLOBALS['_busbook_mysql_connect_error'] = mysqli_connect_error() ?: 'MySQL connection failed.';
        return false;
    }

    $charset = $GLOBALS['dbcharset'] ?? 'latin1';
    if (!mysqli_set_charset($connection, $charset)) {
        mysqli_close($connection);
        return false;
    }

    $GLOBALS['link'] = $connection;
    return $connection;
}

function mysql_select_db($database, $connection = null)
{
    $connection = busbook_mysql_connection($connection);
    return $connection ? mysqli_select_db($connection, (string) $database) : false;
}

function mysql_query($query, $connection = null)
{
    $connection = busbook_mysql_connection($connection);
    if (!$connection) {
        return false;
    }
    $audit = function_exists('audit_sql') ? audit_sql($connection, (string) $query) : null;
    $result = mysqli_query($connection, (string) $query);
    if ($audit) {
        audit_sql_end($audit, $result !== false, mysqli_insert_id($connection));
    }
    return $result;
}

function mysql_fetch_array($result, $resultType = MYSQLI_BOTH)
{
    return $result instanceof mysqli_result ? mysqli_fetch_array($result, $resultType) : false;
}

function mysql_fetch_assoc($result)
{
    return $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : false;
}

function mysql_fetch_row($result)
{
    return $result instanceof mysqli_result ? mysqli_fetch_row($result) : false;
}

function mysql_fetch_field($result, $fieldOffset = 0)
{
    return $result instanceof mysqli_result ? mysqli_fetch_field_direct($result, (int) $fieldOffset) : false;
}

function mysql_free_result($result)
{
    if (!$result instanceof mysqli_result) {
        return false;
    }

    $result->free();
    return true;
}

function mysql_num_fields($result)
{
    return $result instanceof mysqli_result ? mysqli_num_fields($result) : false;
}

function mysql_num_rows($result)
{
    return $result instanceof mysqli_result ? mysqli_num_rows($result) : false;
}

function mysql_result($result, $row, $field = 0)
{
    if (!$result instanceof mysqli_result || !mysqli_data_seek($result, (int) $row)) {
        return false;
    }

    $values = mysqli_fetch_array($result, MYSQLI_BOTH);
    return is_array($values) && array_key_exists($field, $values) ? $values[$field] : false;
}

function mysql_affected_rows($connection = null)
{
    $connection = busbook_mysql_connection($connection);
    return $connection ? mysqli_affected_rows($connection) : -1;
}

function mysql_errno($connection = null)
{
    $connection = busbook_mysql_connection($connection);
    if ($connection) {
        return mysqli_errno($connection);
    }

    return (int) ($GLOBALS['_busbook_mysql_connect_errno'] ?? mysqli_connect_errno());
}

function mysql_get_client_info()
{
    return mysqli_get_client_info();
}

function mysql_get_server_info($connection = null)
{
    $connection = busbook_mysql_connection($connection);
    return $connection ? mysqli_get_server_info($connection) : false;
}

function mysql_real_escape_string($value, $connection = null)
{
    $connection = busbook_mysql_connection($connection);
    return $connection ? mysqli_real_escape_string($connection, (string) $value) : false;
}

function mysql_error($connection = null)
{
    $connection = busbook_mysql_connection($connection);
    if ($connection) {
        return mysqli_error($connection);
    }

    return (string) ($GLOBALS['_busbook_mysql_connect_error'] ?? mysqli_connect_error());
}

function mysql_close($connection = null)
{
    $connection = busbook_mysql_connection($connection);
    if (!$connection) {
        return false;
    }

    $closed = mysqli_close($connection);
    if (($GLOBALS['link'] ?? null) === $connection) {
        unset($GLOBALS['link']);
    }

    return $closed;
}
