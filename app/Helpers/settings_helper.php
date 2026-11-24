<?php

use App\Models\Grade;
use App\Models\SchoolSetting;
use App\Repositories\Grades\GradesInterface;
use App\Repositories\SchoolSetting\SchoolSettingInterface;
use App\Repositories\SystemSetting\SystemSettingInterface;
use App\Services\CachingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;

function getSystemSettings($name = '')
{
    $systemSettingsRepository = app(SystemSettingInterface::class);

    $settingList = array();
    if ($name == '') {
        $settings = $systemSettingsRepository->all();
        foreach ($settings as $row) {
            $settingList[$row->name] = $row->data;
        }
        return $settingList;
    }

    $settings = $systemSettingsRepository->getSpecificData($name);
    return $settings ?? null;
}


function getSchoolSettings($name = '', $schoolId = null)
{
    $schoolSettingsRepository = app(SchoolSettingInterface::class);
    $settingList = array();


    if ($name == '' || $name == ['*']) {

        $settings = SchoolSetting::where('school_id', $schoolId)->get();

        foreach ($settings as $row) {
            $settingList[$row->name] = $row->data;
        }
        return (object) $settingList;
    }

    $settings = $schoolSettingsRepository->getSpecificData($name);
    return $settings ?? null;
}
function getTimeFormat()
{
    $timeFormat = array();

    $time = Carbon::now(env('TIMEZONE') ?? 'UTC');
    $timeFormat['h:i a'] = 'h:i a - ' . $time->format('h:i a');
    $timeFormat['h:i A'] = 'h:i A - ' . $time->format('h:i A');
    $timeFormat['H:i']  = 'H:i - ' . $time->format('H:i');

    return $timeFormat;


    // $timeFormat['h:i a'] = 'h:i a - ' . Carbon::now()->format('h:i a');
    // $timeFormat['h:i A'] = 'h:i A - ' . Carbon::now()->format('h:i A');
    // $timeFormat['H:i'] = 'H:i - ' . Carbon::now()->format('H:i');
    // return $timeFormat;
}

function getDateFormat()
{
    $dateFormat = array();
    $dateFormat['d/m/Y'] = 'd/m/Y - ' . date('d/m/Y');
    $dateFormat['m/d/Y'] = 'm/d/Y - ' . date('m/d/Y');
    $dateFormat['Y/m/d'] = 'Y/m/d - ' . date('Y/m/d');
    $dateFormat['Y/d/m'] = 'Y/d/m - ' . date('Y/d/m');
    $dateFormat['m-d-Y'] = 'm-d-Y - ' . date('m-d-Y');
    $dateFormat['d-m-Y'] = 'd-m-Y - ' . date('d-m-Y');
    $dateFormat['Y-m-d'] = 'Y-m-d - ' . date('Y-m-d');
    $dateFormat['Y-d-m'] = 'Y-d-m - ' . date('Y-d-m');
    // $dateFormat['F j, Y'] = 'F j, Y - ' . date('F j, Y');
    // $dateFormat['jS F Y'] = 'jS F Y - ' . date('jS F Y');
    // $dateFormat['l jS F'] = 'l jS F - ' . date('l jS F');
    // $dateFormat['d M, y'] = 'd M, y - ' . date('d M, y');
    return $dateFormat;
}

function getTimezoneList()
{
    static $timezones = null;

    if ($timezones === null) {
        $list = DateTimeZone::listAbbreviations();
        $idents = DateTimeZone::listIdentifiers();

        $data = $offset = $added = array();
        foreach ($list as $info) {
            foreach ($info as $zone) {
                if (!empty($zone['timezone_id']) && !in_array($zone['timezone_id'], $added) && in_array($zone['timezone_id'], $idents)) {
                    $z = new DateTimeZone($zone['timezone_id']);
                    $c = new DateTime('', $z);
                    $zone['time'] = $c->format('h:i a');
                    $offset[] = $zone['offset'] = $z->getOffset($c);
                    $data[] = $zone;
                    $added[] = $zone['timezone_id'];
                }
            }
        }

        array_multisort($offset, SORT_ASC, $data);
        $i = 0;
        $temp = array();
        foreach ($data as $row) {
            $temp[0] = $row['time'];
            $temp[1] = formatOffset($row['offset']);
            $temp[2] = $row['timezone_id'];
            $timezones[$i++] = $temp;
        }
    }
    return $timezones;
}

function formatOffset($offset)
{
    $hours = $offset / 3600;
    $remainder = $offset % 3600;
    $sign = $hours > 0 ? '+' : '-';
    $hour = (int)abs($hours);
    $minutes = (int)abs($remainder / 60);

    if ($hour == 0 && $minutes == 0) {
        $sign = ' ';
    }
    return $sign . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0');
}

function flattenMyModel($model)
{
    $modelArr = $model->toArray();
    $data = [];
    array_walk_recursive($modelArr, static function ($item, $key) use (&$data) {
        $data[$key] = $item;
    });
    return $data;
}

function changeEnv($data = array())
{
    if (count($data) === 0) {
        return false;
    }

    $envPath = base_path() . '/.env';
    if (!file_exists($envPath)) {
        return false;
    }

    $lines = preg_split('/\r\n|\r|\n/', file_get_contents($envPath));
    $updated = [];
    $keys = [];

    foreach ($lines as $line) {
        if ($line === null || trim($line) === '') {
            $updated[] = $line;
            continue;
        }

        if (str_starts_with(trim($line), '#')) {
            $updated[] = $line;
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            $updated[] = $line;
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $keys[$key] = true;
        $updated[] = $line;
    }

    foreach ($data as $key => $value) {
        $key = trim((string)$key);
        $value = is_string($value) ? $value : (string)$value;

        $formattedValue = $value;
        if ($value === '') {
            $formattedValue = '';
        } elseif (preg_match('/^[A-Za-z0-9_\-.\/\\:]+$/', $value) === 1) {
            $formattedValue = $value;
        } else {
            $formattedValue = '"' . str_replace('"', '\\"', $value) . '"';
        }

        $found = false;
        foreach ($updated as $index => $existingLine) {
            if (trim($existingLine) === '' || str_starts_with(trim($existingLine), '#')) {
                continue;
            }

            $pos = strpos($existingLine, '=');
            if ($pos === false) {
                continue;
            }

            $existingKey = trim(substr($existingLine, 0, $pos));
            if ($existingKey === $key) {
                $updated[$index] = $key . '=' . $formattedValue;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $updated[] = $key . '=' . $formattedValue;
        }
    }

    $envContents = implode(PHP_EOL, $updated);
    if (file_put_contents($envPath, $envContents . PHP_EOL) === false) {
        return false;
    }

    return true;
}

function findExamGrade($percentage)
{
    // $grades = Grade::Owner()->get();
    $grades = app(GradesInterface::class)->builder()->get();
    if (count($grades)) {
        foreach ($grades as $row) {
            if (round($percentage, 2) >= floor($row['starting_range']) && round($percentage, 2) <= floor($row['ending_range'])) {
                return $row->grade;
            }
        }
    }
    return '';
}

function resizeImage($image)
{
    Image::make($image)->save(null, 50);
}

function sessionYearWiseMonth($currentSessionYear = null)
{
    $monthArray = array('1' => __('January'), '2' => __('February'), '3' => __('March'), '4' => __('April'), '5' => __('May'), '6' => __('June'), '7' => __('July'), '8' => __('August'), '9' => __('September'), '10' => __('October'), '11' => __('November'), '12' => __('December'));
    if (!$currentSessionYear) {
        $currentSessionYear = app(CachingService::class)->getSessionYear();
    }
    $startingMonth = date('m', strtotime($currentSessionYear->getRawOriginal('start_date')));
    $months = array();
    for ($i = $startingMonth - 1; $i < $startingMonth + count($monthArray); $i++) {
        $index = $i % count($monthArray) + 1;
        $months[$index] = $monthArray[$index];
    }
    return $months;
}

function sessionYearWiseMonthYear($currentSessionYear = null)
{
    if (!$currentSessionYear) {
        $currentSessionYear = app(CachingService::class)->getSessionYear();
    }

    $schoolSettings = app(CachingService::class)->getSchoolSettings();
    $start = Carbon::parse($currentSessionYear->getRawOriginal('start_date'))->startOfMonth();
    $end = Carbon::parse($currentSessionYear->getRawOriginal('end_date'))->startOfMonth();

    // dd($start, $end, $currentSessionYear->toArray());
    $months = array();
    $current = $start->copy();
    while ($current <= $end) {
        $months[$current->month . '_' . $current->year] = $current->format('F Y');
        $current->addMonth();
    }
    return $months;
}

function format_date($date)
{
    if (Auth::user()) {
        if (Auth::user()->school_id) {
            $setting = app(CachingService::class)->getSchoolSettings();
            return date($setting['date_format'] ?? 'Y-m-d', strtotime($date));
        } else {
            $setting = app(CachingService::class)->getSystemSettings();
            return date($setting['date_format'] ?? 'Y-m-d', strtotime($date));
        }
    }
    return $date;
}

/**
 * The timezone a school operates in.
 *
 * Each school sets its own in General Settings. Falls back to the system-wide
 * timezone, then to the application default, so an unconfigured school keeps
 * working exactly as before.
 */
function schoolTimezone($schoolId = null)
{
    $schoolId = $schoolId ?: (Auth::user()->school_id ?? null);

    if ($schoolId) {
        $timezone = app(CachingService::class)->getSchoolSettings('time_zone', $schoolId);
        if (!empty($timezone) && is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }
    }

    $systemSettings = app(CachingService::class)->getSystemSettings('time_zone');
    if (!empty($systemSettings) && is_string($systemSettings) && in_array($systemSettings, timezone_identifiers_list(), true)) {
        return $systemSettings;
    }

    return config('app.timezone');
}

/**
 * "Now" in a school's own timezone. Attendance dates, QR expiry and
 * finalization all read the clock through this, never the PHP server default.
 */
function schoolNow($schoolId = null): Carbon
{
    return Carbon::now(schoolTimezone($schoolId));
}
