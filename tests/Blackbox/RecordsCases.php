<?php

namespace Tests\Blackbox;

/**
 * The mobile black-box test cases (Testcases/*.xlsx) run against the app's API (/api/v1, the same calls the
 * Flutter app makes). Each case records its outcome here; the results are written to
 * storage/app/blackbox/<workbook>.json and copied into the workbooks' Actual Result / Status / Remarks columns.
 */
trait RecordsCases
{
    /** @var array<string, array{status: string, actual: string, remarks: string}> */
    protected static array $results = [];

    abstract protected static function workbook(): string;

    protected function record(string $id, bool|string $status, string $actual, string $remarks = ''): void
    {
        $status = is_bool($status) ? ($status ? 'Pass' : 'Fail') : $status;
        static::$results[$id] = ['status' => $status, 'actual' => $actual, 'remarks' => $remarks];
    }

    /** A case that needs a real phone (keyboard, camera, airplane mode, …): not run by the API tests. */
    protected function device(string $id, string $whatToCheck, string $note = ''): void
    {
        $this->record($id, 'Not Run', '', trim('Device-only check, not run by the automated API tests. ' . $whatToCheck . ' ' . $note));
    }

    /** A case for a feature ALVY does not have. */
    protected function notBuilt(string $id, string $why): void
    {
        $this->record($id, 'Blocked', 'Feature not in ALVY.', $why);
    }

    public static function tearDownAfterClass(): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/app/blackbox';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir . '/' . static::workbook() . '.json';
        $all = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        file_put_contents($file, json_encode(array_merge($all, static::$results), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        static::$results = [];
        parent::tearDownAfterClass();
    }

    /** One app request with a fresh guard (in a test the guards and default headers would carry over). */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $app = str_contains($uri, '/api/v1');
        if ($app) {
            $this->app['auth']->forgetGuards();
        }
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        if ($app) {
            $this->defaultHeaders = [];
        }
        return $response;
    }

    /** The first validation message of a JSON answer (or its message). */
    protected function msg($response, ?string $field = null): string
    {
        $json = $response->json() ?? [];
        if ($field && isset($json['errors'][$field])) {
            return (string) (is_array($json['errors'][$field]) ? $json['errors'][$field][0] : $json['errors'][$field]);
        }
        return (string) ($json['message'] ?? '');
    }
}
