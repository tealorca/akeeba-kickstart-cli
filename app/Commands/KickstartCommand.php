<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Storage;

class KickstartCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'kickstart';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Installing Akeeba Kickstart files to current folder';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->line('');
        $this->line("    <options=bold,reverse;fg=magenta> Akeeba Kickstart </>");
        $this->line('');

        $installedKickstart = false;
        $latestVersion      = null;

        $currentDirectory = $this->getCurrentDirectory();

        if (!$this->checkKickstartOnDirectory($currentDirectory)) {

            $this->task("    Checking for latest version ", function () use (&$latestVersion) {
                $latestVersion = $this->getLatestVersion();
                return isset($latestVersion);
            });

            if (isset($latestVersion)) {
                $this->line("    Current Latest Version on Akeeba.com : '{$latestVersion}'");
                $this->line('');
            }

            $cacheExist = false;
            if (isset($latestVersion)) {
                $this->task("    Checking for version '{$latestVersion}' on cache ", function () use (&$cacheExist, $latestVersion) {
                    $cacheExist = $this->checkZipOnSystem($latestVersion);
                    return $cacheExist;
                });
            }

            if ($cacheExist) {
                $this->line("    Found '{$latestVersion} on cache'");
                $this->line('');
            }

            if (isset($latestVersion) && !$cacheExist) {
                $this->task("    Dowloading {$latestVersion}' ", function () use (&$cacheExist, $latestVersion) {
                    $this->downloadZipToSystem($latestVersion);
                    $cacheExist = $this->checkZipOnSystem($latestVersion);
                    return $cacheExist;
                });
            }

            if (isset($latestVersion) && $cacheExist) {

                $this->task("    Installing `{$latestVersion}` to `{$currentDirectory}` ", function ()
                     use (&$installedKickstart, $latestVersion, $currentDirectory) {

                        $this->extractVersionToDirectory($latestVersion, $currentDirectory);
                        $installedKickstart = $this->checkKickstartOnDirectory($currentDirectory);
                        return $installedKickstart;
                    });
            }

            if ($installedKickstart) {
                $this->line('');
                $this->task("    Verifying `kickstart.php` on `{$currentDirectory}` ", function () {
                    return true;
                });
            }

            $this->line('');
            $this->line('    <fg=magenta;options=bold>Check All Releases:</> ');
            $this->line('    https://www.akeeba.com/download/akeeba-kickstart.html');
            $this->line('');

        } else {

            $this->error("    Error : `kickstart.php` file already exist on `{$currentDirectory}` ");
            $this->line('');
        }

    }

    /**
     * Returns .
     *
     * @return string
     */
    public function getLatestVersion()
    {
        $disk = Storage::disk('local');

        if ($disk->exists('.akeeba-kickstart-latest')) {
            $lastModified = \Carbon\Carbon::createFromTimestamp($disk->lastModified('.akeeba-kickstart-latest'));
            $diffInDays   = $lastModified->diffInDays();

            if ((int) $diffInDays > 0) {
                $disk->delete('.akeeba-kickstart-latest');
            }
        }

        if (!$disk->exists('.akeeba-kickstart-latest')) {

            try
            {
                $client  = new \Goutte\Client();
                $crawler = $client->request('GET', 'https://www.akeeba.com/download/akeeba-kickstart.html');
                $node    = $crawler->filter('.ars-releases > .ars-release-row')->eq(0)->filter('a')->eq(0);

                $versionText = $node->text();

                if (!empty($versionText)) {
                    $version = trim(str_replace('version', '', strtolower($versionText)));
                    $disk->put('.akeeba-kickstart-latest', $version);
                }

            } catch (\Exception $e) {}
        }

        return $disk->get('.akeeba-kickstart-latest');
    }

    /**
     * Returns the current directory.
     *
     * @return string
     */
    public function getCurrentDirectory()
    {
        return getcwd();
    }

    /**
     *
     *
     * @return string
     */
    public function getVersionStoragePath($version)
    {
        $versionZip         = trim($version) . '.zip';
        $versionZipFilePath = implode(DIRECTORY_SEPARATOR, [
            'releases',
            $versionZip,
        ]);

        return $versionZipFilePath;
    }

    /**
     * Returns the user's timezone.
     *
     * @return string
     */
    public function checkZipOnSystem($version)
    {
        $versionZipFile = $this->getVersionStoragePath($version);

        return Storage::exists($versionZipFile);
    }

    /**
     * Returns the user's timezone.
     *
     * @return string
     */
    public function downloadZipToSystem($version)
    {
        try
        {
            $versionZipFile     = $this->getVersionStoragePath($version);
            $versionNoAsPath    = trim(str_replace('.', '-', strtolower($version)));
            $versionDownloadUrl = "https://www.akeeba.com/download/akeeba-kickstart/{$versionNoAsPath}/kickstart-core-{$versionNoAsPath}-zip.zip";

            $guzzle   = new \GuzzleHttp\Client();
            $response = $guzzle->get($versionDownloadUrl);
            Storage::put($versionZipFile, $response->getBody());

        } catch (\Exception $e) {}
    }

    /**
     *
     *
     * @return string
     */
    public function extractVersionToDirectory($latestVersion, $extractDirectory)
    {
        try
        {
            $versionZipFile = $this->getVersionStoragePath($latestVersion);

            $zip = new \ZipArchive;
            // Zip File Name
            if ($zip->open($versionZipFile) === true) {
                // Unzip Path
                $zip->extractTo($extractDirectory);
                $zip->close();
            }

        } catch (\Exception $e) {}
    }

    /**
     *
     *
     * @return string
     */
    public function checkKickstartOnDirectory($directory)
    {
        $kickstartFilePath = implode(DIRECTORY_SEPARATOR, array_merge(explode(DIRECTORY_SEPARATOR, $directory), [
            'kickstart.php',
        ]));

        return File::exists($kickstartFilePath);
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
