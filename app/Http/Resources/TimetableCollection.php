<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Carbon\Carbon;

class TimetableCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $response = array();

        foreach ($this->collection as $key => $row) {

            // $formatted_start_time = Carbon::parse($row['start_time'])->format($time_format);
            // $formatted_end_time = Carbon::parse($row['end_time'])->format($time_format);

            $response[$key] = array(
                "start_time"         => $row['start_time'],
                "end_time"           => $row['end_time'],
                "day"                => $row['day'],
                "subject"            => $row->subject,
                "teacher_first_name" => $row['subject_teacher'] ? $row['subject_teacher']['teacher']['first_name'] ?? "" : "",
                "teacher_last_name"  => $row['subject_teacher'] ? $row['subject_teacher']['teacher']['last_name'] ?? "" : "",
                "note" => $row['note'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'repeat' => $row['repeat'],
                'meeting_link' => $row['meeting_link'],
            );
        }
        return $response;
    }
}
