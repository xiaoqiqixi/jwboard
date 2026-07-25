<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class JwboardUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jwboard:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'JWBoard 更新';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Artisan::call('config:cache');
        DB::connection()->getPdo();
        $this->applySqlFile('database/update.sql');
        $this->applySqlFile('database/vless.sql');
        $this->ensureTelegramLoginIndex();
        \Artisan::call('horizon:terminate');
        $this->info('更新完毕，队列服务已重启，你无需进行任何操作。');
    }

    private function applySqlFile($path)
    {
        $file = \File::get(base_path($path));
        if (!$file) {
            abort(500, "数据库文件不存在：{$path}");
        }

        $sql = preg_split('/;/', $file);
        if (!is_array($sql)) {
            abort(500, "数据库文件格式有误：{$path}");
        }

        $this->info("正在导入 {$path}...");
        foreach ($sql as $item) {
            if (!trim($item)) continue;
            try {
                DB::select(DB::raw($item));
            } catch (\Exception $e) {
                // Historical update.sql is intentionally idempotent: statements that
                // have already been applied are ignored.
            }
        }
    }

    private function ensureTelegramLoginIndex()
    {
        $index = DB::selectOne("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v2_user' AND INDEX_NAME = 'telegram_id' LIMIT 1");
        if (!$index) {
            DB::statement('ALTER TABLE `v2_user` ADD UNIQUE KEY `telegram_id` (`telegram_id`)');
        }
    }
}
