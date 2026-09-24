<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupFrains extends Command
{
    protected $signature = 'frains:backup';

    protected $description = 'Sauvegarder MySQL et les médias dans une archive privée locale';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Cette commande attend une connexion MySQL.');

            return self::FAILURE;
        }
        $db = config('database.connections.mysql');
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);
        $name = 'frains-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
        $sql = $directory.DIRECTORY_SEPARATOR.$name.'.sql';
        $binary = env('FRAINS_MYSQLDUMP_PATH', PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump');
        $process = new Process([$binary, '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'], '--single-transaction', '--routines', '--triggers', '--result-file='.$sql, $db['database']], null, ['MYSQL_PWD' => $db['password'] ?? '']);
        $process->setTimeout(300);
        $process->run();
        if (! $process->isSuccessful()) {
            if (is_file($sql)) {
                File::delete($sql);
            } $this->error('La sauvegarde MySQL a échoué. Vérifiez le service et la configuration de mysqldump.');

            return self::FAILURE;
        }
        $zip = new ZipArchive;
        $path = $directory.DIRECTORY_SEPARATOR.$name.'.zip';
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            $this->error('Impossible de créer l’archive. Le dump SQL privé est conservé.');

            return self::FAILURE;
        }
        $zip->addFile($sql, 'database.sql');
        foreach (File::allFiles(storage_path('app/public')) as $file) {
            $zip->addFile($file->getRealPath(), 'media/'.$file->getRelativePathname());
        }
        $zip->addFromString('manifest.json', json_encode(['created_at' => now()->toIso8601String(), 'database' => $db['database'], 'application' => 'FRAINS Agro', 'sql_sha256' => hash_file('sha256', $sql)], JSON_PRETTY_PRINT));
        if (! $zip->close()) {
            $this->error('La fermeture de l’archive a échoué ; le SQL est conservé.');

            return self::FAILURE;
        }
        File::delete($sql);
        $this->info('Sauvegarde créée : '.$path);

        return self::SUCCESS;
    }
}
