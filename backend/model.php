<?php

// Function to search for projects based on search query
function searchProject($searchQuery)
{
    // SQL query to search for projects whose names match the search query
    $sql = "SELECT 
                  DISTINCT(p.product_name), 
                  product.product_name AS product, 
                  b.server_url 
              FROM 
                  scrum_product p 
              LEFT JOIN 
                  scrum_product product 
              ON 
                  p.parent_id = product.product_id 
              LEFT JOIN
                  branch_url_tracker b 
              ON 
                  p.product_id = b.r_product_id 
              WHERE 
                  p.parent_id IS NOT NULL 
              AND 
                  LOWER(p.product_name) LIKE ?
              AND
                  (environment_type IS NULL OR environment_type = 'live')";

    // Execute the query with the search query
    $query = $this->db->query($sql, ['%' . strtolower($searchQuery) . '%']);
    // Check if any rows are returned
    if ($query->getNumRows() > 0) {
        // Return the result as an associative array
        return $query->getResultArray();
    } else {
        // Return an empty array if no rows are found
        return [];
    }
}
?>