<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\Plugins\Http\Exceptions;

#[\AllowDynamicProperties]
class FacilityController extends BaseController
{
    /**
     * @return object|null
     */
    public function createOne(): ?object
    {
        $data = json_decode(file_get_contents('php://input'));

        $sql = "INSERT INTO facilities (name, created_at, tag_id, location_id) VALUES (?, ?, ?, ?)";
        $result = $this->db->executeQuery($sql, [$data->name ?? null, $data->created_at ?? null, $data->tag_id ?? null, $data->location_id ?? null]);

        $status = new Status\Ok(['Object created successfully' => $result]);
        $status->send();

        return $status;
    }

    /**
     * @param int $id
     * @return object
     */
    public function readOne(int $id): object
    {
        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, t.name AS tag_name
        FROM facilities AS f
        JOIN locations AS l ON f.location_id = l.id
        LEFT JOIN tags AS t ON f.tag_id = t.id
        WHERE f.id = ?";

        $result = $this->db->executeQuery2($sql, [$id]);

        $status = new Status\Ok(['Object' => $result]);
        $status->send();

        return $status;
    }

    /**
     * @return object 
     */
    public function readAll(): object
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

    /**
     * @param int $id
     * @return object
     */
    public function updateOne(int $id): object
    {
        $data = json_decode(file_get_contents('php://input'));

        $sql = "UPDATE facilities SET name = ?, created_at = ?, tag_id = ?, location_id = ? WHERE id = ?";
        $result = $this->db->executeQuery($sql, [$data->name ?? null, $data->created_at ?? null, $data->tag_id ?? null, $data->location_id ?? null, $id]);

        $status = new Status\Ok(['Object updated successfully' => $result]);
        $status->send();

        return $status;
    }

    /**
     * @param int $id
     * @return object
     */
    public function deleteOne(int $id): object
    {
        $sql = "DELETE FROM facilities WHERE id = ?";

        $result = $this->db->executeQuery($sql, [$id]);

        $status = new Status\Ok(['Object deleted successfully' => $result]);
        $status->send();

        return $status;
    }
}
