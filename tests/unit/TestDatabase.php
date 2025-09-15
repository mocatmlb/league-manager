<?php

class TestDatabase {
    public $admins = [];
    public $updates = [];

    public static function getInstance() {
        // for compatibility if called
        return new self();
    }

    public function fetchOne($sql, $params = []) {
        // Only support admin user lookup used by Auth::authenticateAdmin
        if (stripos($sql, 'FROM admin_users') !== false) {
            $sqlLower = strtolower($sql);
            $hasUsername = strpos($sqlLower, 'username = ?') !== false;
            $hasEmail = strpos($sqlLower, 'email = ?') !== false;

            if (count($params) === 1) {
                $needle = $params[0];
                foreach ($this->admins as $admin) {
                    if ($hasUsername && $admin['username'] === $needle) {
                        return $admin;
                    }
                    if ($hasEmail && $admin['email'] === $needle) {
                        return $admin;
                    }
                }
            } elseif (count($params) === 2) {
                // Support (username = ? OR email = ?) style queries
                $needle1 = $params[0];
                $needle2 = $params[1];
                foreach ($this->admins as $admin) {
                    $matchUser = $hasUsername && ($admin['username'] === $needle1 || $admin['username'] === $needle2);
                    $matchEmail = $hasEmail && ($admin['email'] === $needle1 || $admin['email'] === $needle2);
                    if ($matchUser || $matchEmail) {
                        return $admin;
                    }
                }
            }
            return false;
        }
        return false;
    }

    public function update($table, $data, $where, $whereParams = []) {
        $this->updates[] = [$table, $data, $where, $whereParams];
        return true;
    }
}
