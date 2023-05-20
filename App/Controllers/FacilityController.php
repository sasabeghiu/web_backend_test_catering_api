<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\Plugins\Http\Exceptions;
use Exception;

#[\AllowDynamicProperties]
class FacilityController extends BaseController
{
    /**
     * Create a new facility.
     *
     * HTTP Method: POST
     * URL: /facilities
     *
     * @return object
     */
    public function createOne(): ?object
    {
        $data = $this->getRequestData();

        $locationData = $data->location;
        $locationId = $this->manageLocation((object)$locationData);

        $sql = "INSERT INTO facilities (name, created_at, location_id) VALUES (?, ?, ?)";
        $params = [
            $data->name ?? null,
            $data->created_at ?? null,
            $locationId
        ];

        $result = $this->db->executeQuery($sql, $params);

        if ($result === 0) {
            throw new Exceptions\InternalServerError('Failed to create facility.');
        }

        $this->handleTags($data->tags, $result);

        $status = new Status\Created(['Object created successfully' => $result]);
        $status->send();

        return $status;
    }

    /**
     * Get a facility by ID.
     *
     * HTTP Method: GET
     * URL: /facilities/{id}
     *
     * @param int $id
     * @return object
     */
    public function readOne(int $id): object
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
            throw new Exceptions\NotFound('Facility not found.');
        }

        $status = new Status\Ok(['Facility' => $result]);
        $status->send();

        return $status;
    }

    /**
     * Get all facilities.
     *
     * HTTP Method: GET
     * URL: /facilities
     *
     * @return object
     */
    public function readAll(): object
    {
        $response = [];
        $bind = [];

        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, GROUP_CONCAT(t.name) AS tag_name
                    FROM facilities AS f
                    JOIN locations AS l ON f.location_id = l.id
                    LEFT JOIN facility_tags as ft ON f.id = ft.facility_id
                    LEFT JOIN tags AS t ON ft.tag_id = t.id
                    GROUP BY f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number";

        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 5;
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT $offset, $perPage";

        $result = $this->db->executeQuery2($sql, $bind);
        $response["Facilities"] =  $result;

        $status = new Status\Ok($response);
        $status->send();

        return $status;
    }

    /**
     * Update a facility by ID.
     *
     * HTTP Method: PUT
     * URL: /facilities/{id}
     *
     * @param int $id
     * @return object
     */
    public function updateOne(int $id): object
    {
        $data = $this->getRequestData();

        $locationData = $data->location;
        $locationId = $this->manageLocation((object)$locationData);

        $sql = "UPDATE facilities SET name = ?, created_at = ?, location_id = ? WHERE id = ?";
        $params = [
            $data->name ?? null,
            $data->created_at ?? null,
            $locationId,
            $id
        ];
        $result = $this->db->executeQuery($sql, $params);

        if ($result === 0) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        $this->handleTags($data->tags, $id);

        $status = new Status\Ok(['Facility updated successfully' => $result]);
        $status->send();

        return $status;
    }

    /**
     * Delete a facility by ID.
     *
     * HTTP Method: DELETE
     * URL: /facilities/{id}
     *
     * @param int $id
     * @return object
     */
    public function deleteOne(int $id): object
    {
        $sql = "DELETE FROM facilities WHERE id = ?";
        $result = $this->db->executeQuery($sql, [$id]);

        if ($result === 0) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        $status = new Status\Ok(['Facility deleted successfully' => $result]);
        $status->send();

        return $status;
    }

    /**
     * Get the request data based on the content type and sanitize it.
     *
     * @return object
     */
    private function getRequestData(): object
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $requestData = (object)[];

        if ($contentType === 'application/json') {
            $requestData = (object)json_decode(file_get_contents('php://input'), true);
        } elseif ($contentType === 'application/x-www-form-urlencoded') {
            parse_str(file_get_contents('php://input'), $requestData);
            $requestData = (object)$requestData;
        } elseif ($contentType === 'multipart/form-data') {
            $requestData = (object)($_POST + $_FILES);
        }

        $sanitizedData = $this->sanitize($requestData);

        return $sanitizedData;
    }

    /**
     * Sanitize a value using htmlspecialchars.
     *
     * @param mixed $value
     * @return mixed
     */
    private function sanitize($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }

        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }

    /**
     * Search facilities by facility name, tag name, or location city.
     *
     * HTTP Method: GET
     * URL: /facilities/search?query={searchQuery}
     *
     * @param string $searchQuery
     * @return object
     */
    public function search(string $searchQuery): object
    {
        $searchQuery = '%' . $searchQuery . '%';

        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, GROUP_CONCAT(t.name) AS tag_name
                FROM facilities AS f
                JOIN locations AS l ON f.location_id = l.id
                LEFT JOIN facility_tags as ft ON f.id = ft.facility_id
                LEFT JOIN tags AS t ON ft.tag_id = t.id
                WHERE f.name LIKE ? OR t.name LIKE ? OR l.city LIKE ?
                GROUP BY f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number";

        $params = [$searchQuery, $searchQuery, $searchQuery];
        $result = $this->db->executeQuery2($sql, $params);

        if (empty($result)) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        $response['Facilities'] = $result;

        $status = new Status\Ok($response);
        $status->send();

        return $status;
    }

    /**
     * Create a new location or update existing and return the location ID.
     *
     * @param object $locationData
     * @return int
     */
    private function manageLocation(object $locationData): int
    {
        $existingLocation = $this->getLocationId($locationData);

        if ($existingLocation !== null) {
            return $existingLocation;
        }

        $sql = "INSERT INTO locations (city, address, zip_code, country_code, phone_number)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    city = VALUES(city), address = VALUES(address), zip_code = VALUES(zip_code),
                    country_code = VALUES(country_code), phone_number = VALUES(phone_number)";

        $params = [
            $locationData->city ?? null,
            $locationData->address ?? null,
            $locationData->zip_code ?? null,
            $locationData->country_code ?? null,
            $locationData->phone_number ?? null
        ];

        $result = $this->db->executeQuery($sql, $params);

        if ($result === 0) {
            throw new Exceptions\InternalServerError("Failed to create location.");
        }

        $locationId = $this->db->getLastInsertedId();

        return $locationId;
    }

    /**
     * Get the ID of an existing location if it exists.
     *
     * @param object $locationData
     * @return int|null
     */
    private function getLocationId(object $locationData): ?int
    {
        $sql = "SELECT id FROM locations WHERE city = ? AND address = ? AND zip_code = ? AND country_code = ? AND phone_number = ?";

        $params = [
            $locationData->city ?? null,
            $locationData->address ?? null,
            $locationData->zip_code ?? null,
            $locationData->country_code ?? null,
            $locationData->phone_number ?? null
        ];

        $result = $this->db->executeQuery2($sql, $params);

        if (!empty($result)) {
            return $result[0]['id'];
        }

        return null;
    }
    /**
     * Handle facility tags.
     *
     * @param array $tags
     * @param int $facilityId
     * @return void
     */
    private function handleTags(array $tags, int $facilityId): void
    {
        $this->deleteFacilityTags($facilityId);

        foreach ($tags as $tagName) {
            $tagId = $this->getTagId($tagName);

            if ($tagId === null) {
                $tagId = $this->createTag($tagName);
            }

            $this->addFacilityTag($facilityId, $tagId);
        }
    }

    /**
     * Delete all facility tags for a given facility ID.
     *
     * @param int $facilityId
     * @return void
     */
    private function deleteFacilityTags(int $facilityId): void
    {
        $sql = "DELETE FROM facility_tags WHERE facility_id = ?";
        $this->db->executeQuery($sql, [$facilityId]);
    }

    /**
     * Get the tag ID for a given tag name.
     *
     * @param string $tagName
     * @return int|null
     */
    private function getTagId(string $tagName): ?int
    {
        $sql = "SELECT id FROM tags WHERE name = ?";
        $result = $this->db->executeQuery2($sql, [$tagName]);

        if (!empty($result)) {
            return $result[0]['id'];
        }

        return null;
    }

    /**
     * Create a new tag and return its ID.
     *
     * @param string $tagName
     * @return int
     */
    private function createTag(string $tagName): int
    {
        $sql = "INSERT INTO tags (name) VALUES (?)";
        $this->db->executeQuery($sql, [$tagName]);

        return $this->db->getLastInsertedId();
    }

    /**
     * Add a facility tag by inserting the facility_id and tag_id into the facility_tags table.
     *
     * @param int $facilityId
     * @param int $tagId
     * @return void
     */
    private function addFacilityTag(int $facilityId, int $tagId): void
    {
        $sql = "INSERT INTO facility_tags (facility_id, tag_id) VALUES (?, ?)";
        $this->db->executeQuery($sql, [$facilityId, $tagId]);
    }
}
