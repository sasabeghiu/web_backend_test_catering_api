<?php

namespace App\Controllers;

use App\Plugins\Http\Response as Status;
use App\Repositories\FacilityRepository;
use App\Repositories\LocationRepository;

#[\AllowDynamicProperties]
class FacilityController extends BaseController
{
    private $facilityRepository;
    private $locationRepository;

    public function __construct()
    {
        $this->facilityRepository = new FacilityRepository();
        $this->locationRepository = new LocationRepository();
    }

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
        $data = $this->getRequestData();
        $locationData = $data->location;
        $locationId = $this->locationRepository->manageLocation((object)$locationData);

        $facilityData = [
            'name' => $data->name ?? null,
            'created_at' => $data->created_at ?? null,
            'location_id' => $locationId,
            'tags' => $data->tags ?? []
        ];

        $createdFacility = $this->facilityRepository->createFacility((object)$facilityData);

        $status = new Status\Created($createdFacility);
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
        $facility = $this->facilityRepository->getFacilityById($id);

        $status = new Status\Ok($facility);
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
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['perPage'] ?? 10;

        $facilities = $this->facilityRepository->getAllFacilities($page, $perPage);

        $status = new Status\Ok($facilities);
        $status->send();
        return $status;
    }

    /**
     * Search facilities by facility name, tag name, or location city.
     *
     * HTTP Method: POST
     * URL: /facilities/search
     *
     * @return object
     */
    public function search(): object
    {
        $requestData = $this->getRequestData();
        $facilityName = isset($requestData->name) ? $requestData->name : null;
        $tagName = isset($requestData->tag) ? $requestData->tag : null;
        $locationCity = isset($requestData->city) ? $requestData->city : null;

        $facilities = $this->facilityRepository->searchFacilities($facilityName, $tagName, $locationCity);

        $status = new Status\Ok($facilities);
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
        $locationId = $this->locationRepository->manageLocation((object)$locationData);

        $this->facilityRepository->updateFacilityById($id, $data, $locationId);

        $updatedFacility = $this->facilityRepository->getFacilityById($id);
        $status = new Status\Ok($updatedFacility);
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
        $this->facilityRepository->deleteFacilityById($id);

        $status = new Status\NoContent();
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

        $sanitizedData = $this->facilityRepository->sanitize($requestData);
        return $sanitizedData;
    }
}
