<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class JwboardUpdate extends Command
{
    protected $signature = 'jwboard:update';

    protected $description = '按已安装版本顺序执行 JWBoard 更新';

    public function handle()
    {
        \Artisan::call('config:cache');
        DB::connection()->getPdo();

        $targetVersion = $this->readTargetVersion();
        $this->ensureUpdateLogTable();
        $installedVersion = $this->installedVersion();

        if ($installedVersion && version_compare($installedVersion, $targetVersion, '>')) {
            $this->error("数据库版本 {$installedVersion} 高于当前代码 {$targetVersion}，不能降级。");
            return 1;
        }

        if (!$installedVersion) {
            $this->info('未发现 JWBoard 版本记录，正在执行 1.0.0 基线兼容检查...');
            $this->bootstrapLegacyInstallation();
            $this->recordUpdate('1.0.0', 'baseline');
            $installedVersion = '1.0.0';
        }

        $this->info("数据库版本：{$installedVersion}；目标版本：{$targetVersion}");
        foreach ($this->migrationFiles($targetVersion) as $migration) {
            if ($this->migrationApplied($migration['name'])) {
                continue;
            }

            $this->info("执行 {$migration['version']} 数据库更新：{$migration['name']}");
            $this->applySqlFile($migration['path']);
            $this->recordUpdate($migration['version'], $migration['name']);
        }

        if (!$this->releaseApplied($targetVersion)) {
            $this->recordUpdate($targetVersion, 'release');
        }

        \Artisan::call('horizon:terminate');
        $this->info("JWBoard 已更新至 {$targetVersion}，队列服务将自动重启。");
        return 0;
    }

    private function readTargetVersion()
    {
        $versionFile = base_path('VERSION');
        $contents = \File::exists($versionFile) ? trim(\File::get($versionFile)) : '';
        if (!preg_match('/(\d+\.\d+\.\d+)/', $contents, $matches)) {
            throw new \RuntimeException('VERSION 文件必须包含主版本.次版本.修订版本，例如 JWBoard 1.0.1。');
        }
        return $matches[1];
    }

    private function ensureUpdateLogTable()
    {
        DB::statement('CREATE TABLE IF NOT EXISTS `v2_jwboard_update_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `version` varchar(32) NOT NULL,
            `migration` varchar(255) NOT NULL,
            `applied_at` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `version_migration` (`version`, `migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private function installedVersion()
    {
        $rows = DB::select("SELECT `version` FROM `v2_jwboard_update_log` WHERE `migration` = 'release'");
        $versions = array_map(function ($row) {
            return $row->version;
        }, $rows);
        usort($versions, 'version_compare');
        return empty($versions) ? null : end($versions);
    }

    private function bootstrapLegacyInstallation()
    {
        // Existing V2Board/JWBoard 1.0.0 installations have no version log.
        // Legacy SQL is best-effort because upstream statements may already exist.
        $this->applyLegacySqlFile('database/update.sql');
        $this->applySqlFile('database/vless.sql');
        $this->ensureTelegramLoginIndex();
    }

    private function ensureTelegramLoginIndex()
    {
        $index = DB::selectOne("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v2_user' AND INDEX_NAME = 'telegram_id' LIMIT 1");
        if (!$index) {
            DB::statement('ALTER TABLE `v2_user` ADD UNIQUE KEY `telegram_id` (`telegram_id`)');
        }
    }

    private function migrationFiles($targetVersion)
    {
        $files = glob(base_path('database/jwboard-updates/*.sql')) ?: [];
        $migrations = [];
        foreach ($files as $path) {
            $name = basename($path);
            if (!preg_match('/^(\d+\.\d+\.\d+)\.sql$/', $name, $matches)) {
                continue;
            }
            if (version_compare($matches[1], $targetVersion, '<=')) {
                $migrations[] = [
                    'version' => $matches[1],
                    'name' => $name,
                    'path' => $path
                ];
            }
        }
        usort($migrations, function ($left, $right) {
            return version_compare($left['version'], $right['version']);
        });
        return $migrations;
    }

    private function migrationApplied($name)
    {
        return (bool) DB::selectOne('SELECT 1 FROM `v2_jwboard_update_log` WHERE `migration` = ? LIMIT 1', [$name]);
    }

    private function releaseApplied($version)
    {
        return (bool) DB::selectOne("SELECT 1 FROM `v2_jwboard_update_log` WHERE `version` = ? AND `migration` = 'release' LIMIT 1", [$version]);
    }

    private function recordUpdate($version, $migration)
    {
        DB::table('v2_jwboard_update_log')->insert([
            'version' => $version,
            'migration' => $migration,
            'applied_at' => time()
        ]);
    }

    private function applySqlFile($path)
    {
        $file = \File::get($path);
        if ($file === false) {
            throw new \RuntimeException("数据库文件不存在：{$path}");
        }

        foreach (preg_split('/;/', $file) as $statement) {
            if (!trim($statement)) {
                continue;
            }
            DB::statement($statement);
        }
    }

    private function applyLegacySqlFile($path)
    {
        $file = \File::get(base_path($path));
        if ($file === false) {
            throw new \RuntimeException("数据库文件不存在：{$path}");
        }

        foreach (preg_split('/;/', $file) as $statement) {
            if (!trim($statement)) {
                continue;
            }
            try {
                DB::statement($statement);
            } catch (\Exception $e) {
                // Upstream update.sql predates JWBoard and is intentionally replay-safe.
            }
        }
    }
}
