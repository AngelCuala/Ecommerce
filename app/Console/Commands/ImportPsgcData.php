<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use XMLReader;
use ZipArchive;

/**
 * Build the local Philippine location dataset (resources/data/psgc/) from the official
 * PSA "PSGC Publication Datafile" workbook. No external API and no extra dependency:
 * an .xlsx file is a zip of XML parts, read here with ZipArchive + XMLReader.
 *
 * Codes kept for every location:
 *   psgc  official 10-digit PSGC code  — unique for every row; used for the hierarchy
 *   code  9-digit Correspondence Code  — the value the app STORES (province_code,
 *         municipality_code, ...) and that delivery routing compares. Null when PSA
 *         publishes none; a code is never invented.
 *
 * Hierarchy is read from the 10-digit code (RR PPP MM BBB): a row's parent is the first
 * existing row among RRPPPMM000, RRPPP00000, RR00000000 (excluding itself).
 */
class ImportPsgcData extends Command
{
    protected $signature = 'psgc:import
        {file=storage/app/psgc/PSGC-2Q-2026-Publication-Datafile.xlsx : Path to the PSA workbook}
        {--sheet=PSGC : Worksheet holding the location rows}';

    protected $description = 'Generate resources/data/psgc/*.json from the official PSA PSGC workbook';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $path = base_path($this->argument('file'));
        if (! is_file($path)) {
            $this->error("Workbook not found: {$path}");
            return self::FAILURE;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $this->error('Cannot open the workbook (not a valid .xlsx file).');
            return self::FAILURE;
        }

        [$sheetXml, $published] = $this->locateSheet($zip, (string) $this->option('sheet'));
        if ($sheetXml === null) {
            $this->error("Worksheet '{$this->option('sheet')}' not found.");
            return self::FAILURE;
        }
        $shared = $this->sharedStrings($zip);

        // ── Read rows ─────────────────────────────────────────
        $rows = [];
        $header = null;
        foreach ($this->rows($zip, $sheetXml, $shared) as $cells) {
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(preg_replace('/\s+/', ' ', $h)), $cells);
                continue;
            }
            $rows[] = $cells;
        }
        $col = fn (string $needle) => collect($header)->search(fn ($h) => str_contains($h, $needle));
        $cPsgc = $col('10-digit psgc');
        $cName = $col('name');
        $cCorr = $col('correspondence code');
        $cLvl  = $col('geographic level');
        $cCls  = $col('city class');
        foreach (compact('cPsgc', 'cName', 'cCorr', 'cLvl', 'cCls') as $k => $v) {
            if ($v === false) {
                $this->error("Expected column for {$k} not found in the header row.");
                return self::FAILURE;
            }
        }

        $all = [];
        foreach ($rows as $r) {
            $psgc = $this->digits($r[$cPsgc] ?? '', 10);
            if ($psgc === null) continue;
            $all[$psgc] = [
                'psgc'  => $psgc,
                'code'  => $this->digits($r[$cCorr] ?? '', 9),
                'name'  => trim(preg_replace('/\s+/u', ' ', (string) ($r[$cName] ?? ''))),
                'level' => trim((string) ($r[$cLvl] ?? '')),
                'class' => trim((string) ($r[$cCls] ?? '')) ?: null,
            ];
        }

        $parentOf = function (string $psgc) use ($all): ?string {
            foreach ([substr($psgc, 0, 7) . '000', substr($psgc, 0, 5) . '00000', substr($psgc, 0, 2) . '00000000'] as $p) {
                if ($p !== $psgc && isset($all[$p])) return $p;
            }
            return null;
        };

        // ── Build each level ──────────────────────────────────
        $regions = $provinces = $localities = $districts = [];
        $barangays = [];           // region psgc => locality psgc => [barangay...]
        $unknownLevels = [];

        foreach ($all as $row) {
            // Use the stored string: PHP turns numeric array keys such as "1300000000" into ints.
            $psgc   = $row['psgc'];
            $parent = $parentOf($psgc);
            $region = substr($psgc, 0, 2) . '00000000';
            switch ($row['level']) {
                case 'Reg':
                    $regions[] = ['psgc' => $psgc, 'code' => $row['code'], 'name' => $row['name']];
                    break;

                case 'Prov':
                    $provinces[] = ['psgc' => $psgc, 'code' => $row['code'], 'name' => $row['name'], 'region' => $region, 'type' => 'province'];
                    break;

                case '': // PSA rows with no level: "City of Isabela (Not a Province)", "Special Geographic Area"
                    $provinces[] = ['psgc' => $psgc, 'code' => $row['code'], 'name' => $row['name'], 'region' => $region, 'type' => 'special'];
                    break;

                case 'City':
                case 'Mun':
                    $pl = $parent ? $all[$parent]['level'] : null;
                    $localities[] = [
                        'psgc'   => $psgc,
                        'code'   => $row['code'],
                        'name'   => $row['name'],
                        'level'  => $row['level'],
                        'class'  => $row['class'],
                        'region' => $region,
                        // null = directly under the region (NCR cities, Pateros, HUCs)
                        'parent' => in_array($pl, ['Prov', ''], true) ? $parent : null,
                    ];
                    break;

                case 'SubMun': // Manila's municipal districts (not official municipalities)
                    $districts[] = ['psgc' => $psgc, 'code' => $row['code'], 'name' => $row['name'], 'city' => $parent];
                    break;

                case 'Bgy':
                    $district = ($parent && $all[$parent]['level'] === 'SubMun') ? $parent : null;
                    $locality = $district ? $parentOf($district) : $parent;
                    $barangays[$region][(string) $locality][] = [
                        'psgc'     => $psgc,
                        'code'     => $row['code'],
                        'name'     => $row['name'],
                        'district' => $district,
                    ];
                    break;

                default:
                    $unknownLevels[$row['level']] = ($unknownLevels[$row['level']] ?? 0) + 1;
            }
        }

        if ($unknownLevels) {
            $this->error('Unexpected geographic levels: ' . json_encode($unknownLevels) . ' — nothing written.');
            return self::FAILURE;
        }

        // ── Sanity checks before writing anything ─────────────
        $localityIds = array_flip(array_column($localities, 'psgc'));
        $orphans = 0;
        foreach ($barangays as $byLocality) {
            foreach ($byLocality as $loc => $_) if (! isset($localityIds[(string) $loc])) $orphans++;
        }
        $bgyCount = array_sum(array_map(fn ($r) => array_sum(array_map('count', $r)), $barangays));
        if ($orphans) {
            $this->error("{$orphans} barangay groups do not resolve to a city/municipality — nothing written.");
            return self::FAILURE;
        }

        // ── Write the dataset ─────────────────────────────────
        $dir = resource_path('data/psgc');
        if (is_dir($dir . '/barangays')) {
            array_map('unlink', glob($dir . '/barangays/*.json'));
        }
        @mkdir($dir . '/barangays', 0775, true);

        $json = fn ($data) => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents("$dir/regions.json",    $json($regions));
        file_put_contents("$dir/provinces.json",  $json($provinces));
        file_put_contents("$dir/localities.json", $json($localities));
        file_put_contents("$dir/districts.json",  $json($districts));
        foreach ($barangays as $region => $byLocality) {
            ksort($byLocality);
            file_put_contents("$dir/barangays/{$region}.json", $json($byLocality));
        }

        $missing = array_values(array_filter($all, fn ($r) => $r['code'] === null));
        $counts = [
            'regions'               => count($regions),
            'provinces'             => count(array_filter($provinces, fn ($p) => $p['type'] === 'province')),
            'special_parents'       => count(array_filter($provinces, fn ($p) => $p['type'] === 'special')),
            'cities'                => count(array_filter($localities, fn ($l) => $l['level'] === 'City')),
            'municipalities'        => count(array_filter($localities, fn ($l) => $l['level'] === 'Mun')),
            'sub_municipalities'    => count($districts),
            'barangays'             => $bgyCount,
            'without_correspondence_code' => count($missing),
        ];
        file_put_contents("$dir/meta.json", $json([
            'source'           => 'Philippine Statistics Authority — PSGC Publication Datafile',
            'file'             => basename($path),
            'publication_date' => $published,
            'generated_at'     => now()->toIso8601String(),
            'counts'           => $counts,
        ]));

        $this->info('PSGC dataset written to resources/data/psgc/');
        $this->table(['Level', 'Count'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($missing) {
            $byLevel = collect($missing)->groupBy(fn ($r) => $r['level'] ?: '(no level)')->map->count();
            $this->warn('Rows without a Correspondence Code (kept, code = null): ' . $byLevel->toJson());
            $this->table(['10-digit PSGC', 'Level', 'Name'],
                collect($missing)->map(fn ($r) => [$r['psgc'], $r['level'] ?: '(none)', $r['name']])->all());
        }

        return self::SUCCESS;
    }

    /** @return array{0:?string,1:?string} [sheet xml path, publication date from the Metadata sheet] */
    private function locateSheet(ZipArchive $zip, string $name): array
    {
        $wb   = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $targets = [];
        foreach ($rels->Relationship as $r) $targets[(string) $r['Id']] = (string) $r['Target'];

        $paths = [];
        foreach ($wb->sheets->sheet as $s) {
            $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $paths[(string) $s['name']] = 'xl/' . ltrim(str_replace('/xl/', '', $targets[$rid] ?? ''), '/');
        }

        $published = null;
        if (isset($paths['Metadata'])) {
            $shared = $this->sharedStrings($zip);
            foreach ($this->rows($zip, $paths['Metadata'], $shared) as $r) {
                if (stripos($r[0] ?? '', 'publication date') === 0) { $published = $r[1] ?? null; break; }
            }
        }

        return [$paths[$name] ?? null, $published];
    }

    private ?array $sharedCache = null;

    private function sharedStrings(ZipArchive $zip): array
    {
        if ($this->sharedCache !== null) return $this->sharedCache;
        $out = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml !== false) {
            $x = new XMLReader();
            $x->XML($xml);
            while ($x->read()) {
                if ($x->nodeType === XMLReader::ELEMENT && $x->name === 'si') {
                    $node = simplexml_load_string($x->readOuterXml());
                    $txt = isset($node->t) ? (string) $node->t : '';
                    foreach ($node->r as $run) $txt .= (string) $run->t;
                    $out[] = $txt;
                }
            }
        }
        return $this->sharedCache = $out;
    }

    private function rows(ZipArchive $zip, string $path, array $shared): \Generator
    {
        $x = new XMLReader();
        $x->XML($zip->getFromName($path));
        while ($x->read()) {
            if ($x->nodeType !== XMLReader::ELEMENT || $x->name !== 'row') continue;
            $row = simplexml_load_string($x->readOuterXml());
            $cells = [];
            foreach ($row->c as $c) {
                preg_match('/^[A-Z]+/', (string) $c['r'], $m);
                $i = 0;
                foreach (str_split($m[0]) as $ch) $i = $i * 26 + (ord($ch) - 64);
                $t = (string) $c['t'];
                $cells[$i - 1] = trim(match ($t) {
                    's'         => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default     => (string) $c->v,
                });
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $full = [];
                for ($i = 0; $i <= $max; $i++) $full[$i] = $cells[$i] ?? '';
                yield $full;
            }
        }
    }

    /** Normalize a numeric code cell to a fixed length, or null when blank / not a code. */
    private function digits(string $value, int $length): ?string
    {
        $v = trim($value);
        if ($v === '' || ! ctype_digit($v) || strlen($v) > $length) return null;
        return str_pad($v, $length, '0', STR_PAD_LEFT);
    }
}
