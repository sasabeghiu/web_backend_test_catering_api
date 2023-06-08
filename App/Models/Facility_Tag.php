<?php
class Facility_Tag
{
    private int $facility_id;
    private int $tag_id;

    /**
     * Get the value of facility_id
     */
    public function getFacility_id()
    {
        return $this->facility_id;
    }

    /**
     * Set the value of facility_id
     *
     * @return  self
     */
    public function setFacility_id($facility_id)
    {
        $this->facility_id = $facility_id;

        return $this;
    }

    /**
     * Get the value of tag_id
     */
    public function getTag_id()
    {
        return $this->tag_id;
    }

    /**
     * Set the value of tag_id
     *
     * @return  self
     */
    public function setTag_id($tag_id)
    {
        $this->tag_id = $tag_id;

        return $this;
    }
}
