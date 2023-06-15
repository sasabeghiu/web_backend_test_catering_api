<?php

namespace App\Repositories;

use App\Plugins\Di\Injectable;


#[\AllowDynamicProperties]
class TagRepository extends Injectable
{

    /**
     * Handle facility tags.
     *
     * @param array $tags
     * @param int $facilityId
     * @return void
     */
    public function handleTags(array $tags, int $facilityId): void
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
    public function deleteFacilityTags(int $facilityId): void
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
    public function getTagId(string $tagName): ?int
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
    public function createTag(string $tagName): int
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
    public function addFacilityTag(int $facilityId, int $tagId): void
    {
        $sql = "INSERT INTO facility_tags (facility_id, tag_id) VALUES (?, ?)";
        $this->db->executeQuery($sql, [$facilityId, $tagId]);
    }
}
