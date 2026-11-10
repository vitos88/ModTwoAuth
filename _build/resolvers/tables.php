<?php
/*
 * This file is part of ModTwoAuth.
 *
 * Copyright (C) 2026 vitos88
 *
 * ModTwoAuth is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ModTwoAuth is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ModTwoAuth; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

/** @var xPDO\Transport\xPDOTransport $transport */
/** @var array $options */
/** @var  MODX\Revolution\modX $modx */

if ($transport->xpdo) {
    $modx = $transport->xpdo;

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modx->addPackage('ModTwoAuth\Model', MODX_CORE_PATH . 'components/modtwoauth/src/', null, 'ModTwoAuth\\');
            $manager = $modx->getManager();
            $objects = [];
            $schemaFile = MODX_CORE_PATH . 'components/modtwoauth/schema/modtwoauth.mysql.schema.xml';
            if (is_file($schemaFile)) {
                $schema = new SimpleXMLElement($schemaFile, 0, true);
                if (isset($schema->object)) {
                    foreach ($schema->object as $obj) {
                        $objects[] = (string)$obj['class'];
                    }
                }
                unset($schema);
            }
            foreach ($objects as $class) {
                $class = 'ModTwoAuth\\Model\\' . $class;
                $table = $modx->getTableName($class);
                $sql = "SHOW TABLES LIKE '" . trim($table, '`') . "'";
                $stmt = $modx->prepare($sql);
                $newTable = true;
                if ($stmt->execute() && $stmt->fetchAll()) {
                    $newTable = false;
                }
                // If the table is just created
                if ($newTable) {
                    $manager->createObjectContainer($class);
                } else {
                    // If the table exists
                    // 1. Operate with tables
                    $tableFields = [];
                    $c = $modx->prepare("SHOW COLUMNS IN {$modx->getTableName($class)}");
                    $c->execute();
                    while ($cl = $c->fetch(PDO::FETCH_ASSOC)) {
                        $tableFields[$cl['Field']] = $cl['Field'];
                    }
                    foreach ($modx->getFields($class) as $field => $v) {
                        if (in_array($field, $tableFields)) {
                            unset($tableFields[$field]);
                            $manager->alterField($class, $field);
                        } else {
                            $manager->addField($class, $field);
                        }
                    }
                    foreach ($tableFields as $field) {
                        $manager->removeField($class, $field);
                    }
                    // 2. Operate with indexes
                    $indexes = [];
                    $c = $modx->prepare("SHOW INDEX FROM {$modx->getTableName($class)}");
                    $c->execute();
                    while ($row = $c->fetch(PDO::FETCH_ASSOC)) {
                        $name = $row['Key_name'];
                        // NEVER touch the primary key: it is managed by xPDOSimpleObject
                        // and MySQL requires DROP PRIMARY KEY syntax anyway.
                        if ($name === 'PRIMARY') {
                            continue;
                        }
                        if (!isset($indexes[$name])) {
                            $indexes[$name] = [$row['Column_name']];
                        } else {
                            $indexes[$name][] = $row['Column_name'];
                        }
                    }
                    foreach ($indexes as $name => $values) {
                        sort($values);
                        $indexes[$name] = implode(':', $values);
                    }
                    $map = $modx->getIndexMeta($class);
                    if (!is_array($map)) {
                        $map = [];
                    }
                    // Remove old indexes (never PRIMARY, it is skipped above)
                    foreach ($indexes as $key => $index) {
                        if (strcasecmp($key, 'PRIMARY') === 0 || isset($map[$key])) {
                            continue;
                        }
                        if ($manager->removeIndex($class, $key)) {
                            $modx->log(modX::LOG_LEVEL_INFO, "Removed index \"{$key}\" of the table \"{$class}\"");
                        }
                    }
                    // Older releases could create duplicate session tokens because the
                    // UNIQUE index was missing. Keep the newest record for each token
                    // before adding the index; user settings and credentials are untouched.
                    if ($class === 'ModTwoAuth\\Model\\TwoAuthSession') {
                        $sql = 'DELETE duplicateSession FROM ' . $table . ' AS duplicateSession '
                            . 'INNER JOIN ' . $table . ' AS latestSession '
                            . 'ON duplicateSession.session_token = latestSession.session_token '
                            . 'AND duplicateSession.id < latestSession.id';
                        $stmt = $modx->prepare($sql);
                        if (!$stmt->execute()) {
                            $modx->log(modX::LOG_LEVEL_ERROR,
                                'Could not remove duplicate ModTwoAuth session records before adding the session_token index.'
                            );
                        } elseif ($stmt->rowCount() > 0) {
                            $modx->log(modX::LOG_LEVEL_INFO,
                                'Removed duplicate ModTwoAuth session records before adding the session_token index.'
                            );
                        }
                    }
                    // Add or alter existing. xPDO adds PRIMARY to getIndexMeta()
                    // for xPDOSimpleObject even when it is absent from the XML map.
                    // Since PRIMARY was intentionally excluded from $indexes above,
                    // without this guard the installer tries to create it again.
                    foreach ($map as $key => $index) {
                        if (strcasecmp((string) $key, 'PRIMARY') === 0 || !empty($index['primary'])) {
                            continue;
                        }
                        if (!isset($index['columns']) || !is_array($index['columns'])) {
                            continue;
                        }
                        ksort($index['columns']);
                        $index = implode(':', array_keys($index['columns']));
                        if (!isset($indexes[$key])) {
                            if ($manager->addIndex($class, $key)) {
                                $modx->log(modX::LOG_LEVEL_INFO, "Added index \"{$key}\" in the table \"{$class}\"");
                            }
                        } else {
                            if ($index != $indexes[$key]) {
                                if ($manager->removeIndex($class, $key) && $manager->addIndex($class, $key)) {
                                    $modx->log(modX::LOG_LEVEL_INFO,
                                        "Updated index \"{$key}\" of the table \"{$class}\""
                                    );
                                }
                            }
                        }
                    }
                }
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            break;
    }
}

return true;
