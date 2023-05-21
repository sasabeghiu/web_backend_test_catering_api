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

        //get location data and add it to database if it doesn't exist
        $locationData = $data->location;
        $locationId = $this->manageLocation((object)$locationData);

        //preparing the query and parameters for inserting a facility
        $sql = "INSERT INTO facilities (name, created_at, location_id) VALUES (?, ?, ?)";
        $params = [
            $data->name ?? null,
            $data->created_at ?? null,
            $locationId
        ];

        //execute query
        $result = $this->db->executeQuery($sql, $params);

        //throw exception if failed to insert
        if ($result === 0) {
            throw new Exceptions\InternalServerError('Failed to create facility.');
        }

        //get tags data and add it to database if it doesn't exist
        $this->handleTags($data->tags, $result);

        //send a 201 response with the created object
        $status = new Status\Created(['Facility created successfully' => $result]);
        $status->send();

        //return response
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
        //preparing the query for getting facility details based on its ID together with related location info and tags 
        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, GROUP_CONCAT(t.name) AS tag_name
                    FROM facilities AS f
                    JOIN locations AS l ON f.location_id = l.id
                    LEFT JOIN facility_tags as ft ON f.id = ft.facility_id
                    LEFT JOIN tags AS t ON ft.tag_id = t.id
                    WHERE f.id = ?
                    GROUP BY f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number";

        //execute query with the ID as param
        $result = $this->db->executeQuery2($sql, [$id]);

        //throw exception if result is empty
        if (empty($result)) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        //send a 200 response with facility details
        $status = new Status\Ok(['Facility' => $result]);
        $status->send();

        //return response
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
        //intialize empty arrays
        $response = [];
        $bind = [];

        //preparing the query for getting all facility details together with related location info and tags 
        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, GROUP_CONCAT(t.name) AS tag_name
                    FROM facilities AS f
                    JOIN locations AS l ON f.location_id = l.id
                    LEFT JOIN facility_tags as ft ON f.id = ft.facility_id
                    LEFT JOIN tags AS t ON ft.tag_id = t.id
                    GROUP BY f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number";

        //get page number nad items per page from get request parameters
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 5;

        //calculate offset based on page number and items per page
        $offset = ($page - 1) * $perPage;

        //add LIMIT clause to query 
        $sql .= " LIMIT $offset, $perPage";

        //execute query
        $result = $this->db->executeQuery2($sql, $bind);

        //store fetched facilities in response array
        $response["Facilities"] =  $result;

        //send a 200 response with all facilities details
        $status = new Status\Ok($response);
        $status->send();

        //return response
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
        //get request data based on content type
        $data = $this->getRequestData();

        //get location data and add it to database if it doesn't exist
        $locationData = $data->location;
        $locationId = $this->manageLocation((object)$locationData);

        //preparing the query and parameters for uptating a facility
        $sql = "UPDATE facilities SET name = ?, created_at = ?, location_id = ? WHERE id = ?";
        $params = [
            $data->name ?? null,
            $data->created_at ?? null,
            $locationId,
            $id
        ];

        //execute query
        $result = $this->db->executeQuery($sql, $params);

        //throw exception if failed to update
        if ($result === 0) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        //get tags data and add it to database if it doesn't exist
        $this->handleTags($data->tags, $id);

        //send a 200 response true
        $status = new Status\Ok(['Facility updated successfully' => $result]);
        $status->send();

        //return response
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
        //preparing query to delete a facility based on its ID
        $sql = "DELETE FROM facilities WHERE id = ?";

        //execute query with the ID as param
        $result = $this->db->executeQuery($sql, [$id]);

        //throw exception if result is empty
        if ($result === 0) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        //send a 200 response true
        $status = new Status\Ok(['Facility deleted successfully' => $result]);
        $status->send();

        //return response
        return $status;
    }

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

        //sanitize request data
        $sanitizedData = $this->sanitize($requestData);

        //return sanitized request data
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

        //return value
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
        //add wildcard characters to return partial matches
        $searchQuery = '%' . $searchQuery . '%';

        //prepare query and params to search through facilities based on facility name, tag name or location city
        $sql = "SELECT f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number, GROUP_CONCAT(t.name) AS tag_name
                FROM facilities AS f
                JOIN locations AS l ON f.location_id = l.id
                LEFT JOIN facility_tags as ft ON f.id = ft.facility_id
                LEFT JOIN tags AS t ON ft.tag_id = t.id
                WHERE f.name LIKE ? OR t.name LIKE ? OR l.city LIKE ?
                GROUP BY f.id, f.name, f.created_at, l.city, l.address, l.zip_code, l.country_code, l.phone_number";
        $params = [$searchQuery, $searchQuery, $searchQuery];

        //execute query
        $result = $this->db->executeQuery2($sql, $params);

        //throw exception if result is empty
        if (empty($result)) {
            throw new Exceptions\NotFound('Facility not found.');
        }

        //send a 200 response with all facilities details
        $response['Facilities'] = $result;

        //store fetched facilities in response array
        $status = new Status\Ok($response);
        $status->send();

        //return response
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
        //checking if the location already exists in database
        $existingLocation = $this->getLocationId($locationData);

        //return its ID if it exists
        if ($existingLocation !== null) {
            return $existingLocation;
        }

        //prepare query and params to insert OR update a location 
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

        //execute query
        $result = $this->db->executeQuery($sql, $params);

        //throw exception if creating or uptating a location failed
        if ($result === 0) {
            throw new Exceptions\InternalServerError("Failed to create/update location.");
        }

        //get the ID of last inserted location
        $locationId = $this->db->getLastInsertedId();

        //return location ID
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
        //prepare query and params to get an existing location
        $sql = "SELECT id FROM locations WHERE city = ? AND address = ? AND zip_code = ? AND country_code = ? AND phone_number = ?";
        $params = [
            $locationData->city ?? null,
            $locationData->address ?? null,
            $locationData->zip_code ?? null,
            $locationData->country_code ?? null,
            $locationData->phone_number ?? null
        ];

        //execute the query
        $result = $this->db->executeQuery2($sql, $params);

        //return id of existing lcoation if found
        if (!empty($result)) {
            return $result[0]['id'];
        }

        //return null if location not found
        return null;
    }

    // manage tags for facilities

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
