<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\Plugins\Http\Exceptions;

#[\AllowDynamicProperties]
class FacilityController extends BaseController
{
    /**
     * Function to retrieve all facilities
     * @return object Status object with the facilities in an array
     */
    public function facilities(): object
    {
        $response = [];
        $bind = [];

        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, t.name AS tag_name
                    FROM facilities AS f
                    JOIN locations AS l ON f.location_id = l.id
                    LEFT JOIN tags AS t ON f.tag_id = t.id";

        $result = $this->db->executeQuery2($sql, $bind);
        $response["data"] =  $result;

        $status = new Status\Ok($response);
        $status->send();

        return $status;
    }

    public function getOne(int $id): object
    {
        $result = $this->db->executeQuery2("SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, t.name AS tag_name
                                                FROM facilities AS f
                                                JOIN locations AS l ON f.location_id = l.id
                                                LEFT JOIN tags AS t ON f.tag_id = t.id
                                                WHERE f.id = ?", [$id]);

        $status = new Status\Ok($result);
        $status->send();

        return $status;
    }
}
