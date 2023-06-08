<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\Plugins\Http\Exceptions;

#[\AllowDynamicProperties]
class FacilityController extends BaseController
{
    /**
     * Create a new facility.
     *
     * HTTP Method: POST
     * URL: /facility
     *
     * @return object
     */
    public function createOne(): ?object
    {
        //get request data based on content type
        $data = $this->getRequestData();

        $locationData = $data->location;
        $locationId = $this->manageLocation((object)$locationData);

        $sql = "INSERT INTO facilities (name, created_at, location_id) VALUES (?, ?, ?)";
        $params = [
            $data->name ?? null,
            $data->created_at ?? null,
            $locationId
        ];

        $result = $this->db->executeQuery2($sql, $params);

        if ($result === 0) {
            throw new Exceptions\InternalServerError('Failed to create facility.');
        }

        $returnObjId = $this->db->getLastInsertedId();

        //get tags data and add it to database if it doesn't exist
        $this->handleTags($data->tags, reset($result));

        $createdFacility = $this->readOne($returnObjId);

        $status = new Status\Created(['Facility created successfully' => $createdFacility]);

        $status->send();

        return $status;
    }

    /**
     * Get a facility by ID.
     *
     * HTTP Method: GET
     * URL: /facility/{id}
     *
     * @param int $id
     * @return object
     */
    public function readOne(int $id): object
    {
        $id = (int) $id;

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

        $sanitizedResult = $this->sanitize($result);

        foreach ($sanitizedResult as &$row) {
            $row['tag_name'] = explode(',', $row['tag_name']);
        }

        $status = new Status\Ok($sanitizedResult);
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

        $sql = "SELECT COUNT(*) as total_records FROM facilities";
        $countResult = $this->db->executeQuery2($sql, $bind);
        $totalRecords = $countResult[0]['total_records'];
        //get page number nad items per page from get request parameters
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['perPage'] ?? 10;
        //calculate offset based on page number and items per page
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
            $row['tag_name'] = explode(',', $row['tag_name']);
        }

        $hasNextPage = ($totalRecords > ($offset + $perPage));
        $response["HasNextPage"] = $hasNextPage;
        $response = $sanitizedResult;

        $status = new Status\Ok($response);
        $status->send();

        return $status;
    }

    /**
     * Search facilities by facility name, tag name, or location city.
     *
     * HTTP Method: GET
     * URL: /facilities/search?name={facilityName}&tag={tagName}&city={locationCity}
     *
     * @param string|null $facilityName
     * @param string|null $tagName;
     * @param string|null $locationCity;
     * @return object
     */
    public function search(?string $facilityName = null, ?string $tagName = null, ?string $locationCity = null): object
    {
        $facilityName = isset($_GET['name']) ? $_GET['name'] : '';
        $tagName = isset($_GET['tag']) ? $_GET['tag'] : '';
        $locationCity = isset($_GET['city']) ? $_GET['city'] : '';

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

        if (empty($result)) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        $sanitizedResult = $this->sanitize($result);

        foreach ($sanitizedResult as &$row) {
            $row['tag_name'] = explode(',', $row['tag_name']);
        }

        $response = $sanitizedResult;

        $status = new Status\Ok($response);
        $status->send();

        return $status;
    }

    /**
     * Update a facility by ID.
     *
     * HTTP Method: PUT
     * URL: /facility/{id}
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

        $status = new Status\Ok(['Facility updated successfully' => $data]);
        $status->send();

        return $status;
    }

    /**
     * Delete a facility by ID.
     *
     * HTTP Method: DELETE
     * URL: /facility/{id}
     *
     * @param int $id
     * @return object
     */
    public function deleteOne(int $id): object
    {
        $id = (int) $id;
        $sanitizedId = $this->sanitize([$id]);
        $id = $sanitizedId[0];

        $sql = "DELETE FROM facilities WHERE id = ?";

        $result = $this->db->executeQuery($sql, [$id]);

        if ($result === 0) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        $status = new Status\Ok(['Facility deleted successfully' => $result]);
        $status->send();

        return $status;
    }
    // ---------------------------------------------------------------------------------------------------
    /**
     * Get the request data based on the content type and sanitize it.
     *
     * @return object
     */
    private function getRequestData(): object
    {
        //get content tyer from request header
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        //initialize empty object to store request data
        $requestData = (object)[];

        //based on contenty type parse request data
        if ($contentType === 'application/json') {
            //if content type is JSON - decode JSON from request body
            $requestData = (object)json_decode(file_get_contents('php://input'), true);
        } elseif ($contentType === 'application/x-www-form-urlencoded') {
            //if content type is URL-encoded form data - parse request body and convert it to a object
            parse_str(file_get_contents('php://input'), $requestData);
            $requestData = (object)$requestData;
        } elseif ($contentType === 'multipart/form-data') {
            //if content type is multipart form data - combine POST and FILES in a object
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



    /**
     * Create a new location or update existing and return the location ID.
     *
     * @param object $locationData
     * @return int
     */
    private function manageLocation(object $locationData): int
    {
        //checking if the location already exists in database
        $existingLocation = $this->getLocationId($locationData);

        //return its ID if it exists
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
            throw new Exceptions\InternalServerError("Failed to create/update location.");
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
    // ------------------------------------------------------------------------------------
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
