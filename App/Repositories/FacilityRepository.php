<?php

namespace App\Repositories;

use App\Plugins\Http\Exceptions;
use App\Plugins\Di\Injectable;

#[\AllowDynamicProperties]
class FacilityRepository extends Injectable
{
    private $tagRepository;

    public function __construct()
    {
        $this->tagRepository = new TagRepository();
    }

    public function createFacility(object $data): ?object
    {
        $sql = "INSERT INTO facilities (name, created_at, location_id) VALUES (?, ?, ?)";
        $params = [
            $data->name ?? null,
            $data->created_at ?? null,
            $data->location_id ?? null
        ];
        $result = $this->db->executeQuery2($sql, $params);

        if ($result === 0) {
            throw new Exceptions\InternalServerError('Internal_Server_Error');
        }

        $returnObjId = $this->db->getLastInsertedId();

        $tags = $data->tags ?? [];

        // Check if tags is null or an array
        if (!is_array($tags)) {
            throw new \InvalidArgumentException('Tags must be null or an array.');
        }

        if (!empty($tags)) {
            $this->tagRepository->handleTags($tags, $returnObjId);
        }

        $createdFacility = $this->getFacilityById($returnObjId);

        return (object) $createdFacility;
    }

    public function getFacilityById(int $id)
    {
        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, GROUP_CONCAT(t.name) AS tag_name
                    FROM facilities AS f
                    JOIN locations AS l ON f.location_id = l.id
                    LEFT JOIN facility_tags as ft ON f.id = ft.facility_id
                    LEFT JOIN tags AS t ON ft.tag_id = t.id
                    WHERE f.id = ? 
                    GROUP BY f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number";
        $result = $this->db->executeQuery2($sql, [$id]);

        if (empty($result)) {
            throw new Exceptions\NotFound('Facility_not_found.');
        }

        $sanitizedResult = $this->sanitize($result);

        foreach ($sanitizedResult as &$row) {
            $row['tag_name'] = $row['tag_name'] !== null ? explode(',', $row['tag_name']) : [];
        }
        return $sanitizedResult;
    }

    public function getAllFacilities(int $page, int $perPage): array
    {
        $response = [];
        $bind = [];

        $sql = "SELECT COUNT(*) as total_records FROM facilities";
        $countResult = $this->db->executeQuery2($sql, $bind);
        $totalRecords = $countResult[0]['total_records'];

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, GROUP_CONCAT(t.name) AS tag_name
                    FROM facilities AS f
                    JOIN locations AS l ON f.location_id = l.id
                    LEFT JOIN facility_tags as ft ON f.id = ft.facility_id
                    LEFT JOIN tags AS t ON ft.tag_id = t.id
                    GROUP BY f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number 
                    LIMIT $perPage OFFSET $offset";

        $result = $this->db->executeQuery2($sql, $bind);
        $sanitizedResult = $this->sanitize($result);

        foreach ($sanitizedResult as &$row) {
            $row['tag_name'] = $row['tag_name'] !== null ? explode(',', $row['tag_name']) : [];
        }

        $hasNextPage = ($totalRecords > ($offset + $perPage));
        $response["HasNextPage"] = $hasNextPage;
        $response = $sanitizedResult;

        return $response;
    }

    public function searchFacilities(?string $facilityName, ?string $tagName, ?string $locationCity): array
    {
        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, GROUP_CONCAT(t.name) AS tag_name
        FROM facilities AS f
        JOIN locations AS l ON f.location_id = l.id
        LEFT JOIN facility_tags as ft ON f.id = ft.facility_id
        LEFT JOIN tags AS t ON ft.tag_id = t.id
        WHERE 1";
        $params = [];

        if (!empty($facilityName)) {
            $sql .= " AND f.name LIKE ?";
            $params[] = '%' . $facilityName . '%';
        }

        if (!empty($tagName)) {
            $sql .= " AND t.name LIKE ?";
            $params[] = '%' . $tagName . '%';
        }

        if (!empty($locationCity)) {
            $sql .= " AND l.city LIKE ?";
            $params[] = '%' . $locationCity . '%';
        }

        $sql .= " GROUP BY f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number";
        $result = $this->db->executeQuery2($sql, $params);

        $response = [];

        if (!empty($result)) {
            $sanitizedResult = [];
            foreach ($result as $row) {
                $row['tag_name'] = $row['tag_name'] !== null ? explode(',', $row['tag_name']) : [];
                $sanitizedResult[] = $row;
            }
            $response = $sanitizedResult;
        }


        return $response;
    }

    public function updateFacilityById(int $id, object $data, int $locationId): void
    {
        $id = (int) $id;

        if (!$this->checkIfFacilityExists($id)) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        $sql = "UPDATE facilities SET name = ?, created_at = ?, location_id = ? WHERE id = ?";
        $params = [
            $data->name ?? null,
            $data->created_at ?? null,
            $locationId,
            $id
        ];
        $result = $this->db->executeQuery($sql, $params);

        if (empty($result)) {
            throw new Exceptions\NotFound('Facility_not_found.');
        }

        $this->tagRepository->handleTags($data->tags, $id);
    }

    public function deleteFacilityById(int $id): void
    {
        $id = (int) $id;
        $sql = "DELETE FROM facilities WHERE id = ?";
        $this->db->executeQuery2($sql, [$id]);

        $affectedRows = $this->db->getStatement()->rowCount();
        if ($affectedRows === 0) {
            throw new Exceptions\NotFound('Facility_not_found.');
        }
    }


    private function checkIfFacilityExists(int $facilityId): bool
    {
        $sql = "SELECT COUNT(*) FROM facilities WHERE id = ?";
        $params = [$facilityId];
        $result = $this->db->executeQuery2($sql, $params);

        return $result[0]['COUNT(*)'] > 0;
    }

    /**
     * Sanitize a value using htmlspecialchars.
     *
     * @param mixed $value
     * @return mixed
     */
    public function sanitize($value)
    {
        //if value is array - sanitize each element
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        //if value is string sanitize it using htmspecialchars | ENT_QUOTES and ENT_HTML5 are used to convert quotes and handle entities
        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }
}
