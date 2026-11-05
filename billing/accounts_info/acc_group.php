<?php
/** Accounts Group Distribution: the group tree (Assets, Liabilities, Equity, Income, Expenses and their sub-groups). */
require_once __DIR__ . '/acc_common.php';
acc_require_ready($conn, 'Accounts Group Distribution', 'group');

$tree = acc_group_tree($conn);
$parents = [];
foreach ($tree as $gid => $g) {
    $parents[$gid] = $g['path'];
}

acc_crud($conn, [
    'self' => 'acc_group.php', 'title' => 'Accounts Group Distribution', 'menu' => 'group',
    'table' => 'acc_groups', 'pk' => 'group_id',
    'fields' => [
        'parent_id' => ['label' => 'Under Group', 'type' => 'select', 'options' => $parents, 'blank' => 'Top level', 'nullable' => true, 'span' => 2],
        'group_code' => ['label' => 'Group Code', 'max' => 20],
        'group_name' => ['label' => 'Group Name', 'required' => true, 'max' => 120],
        'nature' => ['label' => 'Nature', 'type' => 'select', 'options' => ACC_NATURES, 'string' => true, 'required' => true, 'help' => 'Sub-groups always take the nature of their parent.'],
        'sort_order' => ['label' => 'Sort Order', 'type' => 'int', 'default' => 0],
        'is_active' => ['label' => 'Active', 'type' => 'check'],
    ],
    'validate' => function (&$v, $id) use ($conn, $tree) {
        if ($v['parent_id'] !== null) {
            if ($id > 0) {
                // A group cannot sit under itself or under one of its own sub-groups.
                for ($p = (int) $v['parent_id']; $p > 0; $p = (int) ($tree[$p]['parent_id'] ?? 0)) {
                    if ($p === $id) {
                        throw new RuntimeException('A group cannot be placed under itself or its own sub-group.');
                    }
                }
            }
            $v['nature'] = $tree[(int) $v['parent_id']]['nature'];
        }
        if ($id > 0) {
            $old = sales_row($conn, 'SELECT nature FROM acc_groups WHERE group_id=?', 'i', [$id]);
            if ($old && $old['nature'] !== $v['nature'] && sales_row($conn, 'SELECT 1 FROM acc_groups WHERE parent_id=? LIMIT 1', 'i', [$id])) {
                throw new RuntimeException('Change the nature of a top-level group only before it has sub-groups.');
            }
        }
    },
    'delete_check' => function ($id) use ($conn) {
        if (sales_row($conn, 'SELECT 1 FROM acc_groups WHERE parent_id=? LIMIT 1', 'i', [$id])) {
            return 'This group has sub-groups. Delete or move them first.';
        }
        if (sales_row($conn, 'SELECT 1 FROM acc_accounts WHERE group_id=? LIMIT 1', 'i', [$id])) {
            return 'This group has accounts. Move them to another group first.';
        }
        return '';
    },
    'rows' => function ($q) use ($conn) {
        $counts = [];
        foreach (sales_rows($conn, 'SELECT group_id, COUNT(*) n FROM acc_accounts GROUP BY group_id') as $r) {
            $counts[(int) $r['group_id']] = (int) $r['n'];
        }
        $rows = [];
        foreach (acc_group_tree($conn) as $g) {
            if ($q !== '' && stripos($g['path'] . ' ' . $g['group_code'], $q) === false) {
                continue;
            }
            $g['accounts'] = $counts[(int) $g['group_id']] ?? 0;
            $rows[] = $g;
        }
        return $rows;
    },
    'search' => ['group_name'],
    'columns' => [
        'group_code' => 'Code',
        'group_name' => ['Group Name', fn($r) => '<span class="tree-' . min(4, (int) $r['depth']) . '">' . ((int) $r['depth'] ? '&#8627; ' : '<b>') . sales_h($r['group_name']) . ((int) $r['depth'] ? '' : '</b>') . '</span>'],
        'nature' => ['Nature', fn($r) => sales_h(ACC_NATURES[$r['nature']])],
        'accounts' => ['Accounts', fn($r) => (int) $r['accounts'], true],
        'is_active' => ['Active', fn($r) => (int) $r['is_active'] ? 'Yes' : 'No'],
    ],
]);
?>
<?php include "../../addon/conf/connection_close.php";  ?>
