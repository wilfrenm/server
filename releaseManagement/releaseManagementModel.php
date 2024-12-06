<?php
namespace App\Models;

use App\Models\BaseModel;
use CodeIgniter\HTTP\Message;

class ReleaseManagementModel extends BaseModel
{
    protected $table = "log_details";
    protected $primaryKey = "log_id";
    protected $validationRules = [
        'bucket_name' => 'required|min_length[3]|max_length[255]',
        'access_key' => 'required|min_length[3]|max_length[255]',
        'secret_key' => 'required|min_length[3]|max_length[255]',
        'region' => 'required|min_length[3]|max_length[255]',
    ];

    // Setting custom validation messages for the fields
    protected $validationMessages = [
        "bucket_name" => [
            "required" => "The bucket name is required.",
            "min_length" => "The bucket_name must be at least 3 characters long.",
            "max_length" => "The git_url cannot exceed 255 characters."
        ],
        "access_key" => [
            "required" => "The access key is required.",
            "min_length" => "The access key must be at least 3 characters long.",
            "max_length" => "The access_key cannot exceed 255 characters."
        ],
        "secret_key" => [
            "required" => "The secret key is required.",
            "min_length" => "The secret key must be at least 3 characters long.",
            "max_length" => "The secret key cannot exceed 255 characters."
        ],
        "region" => [
            "required" => "The region is required.",
            "min_length" => "The region must be at least 3 characters long.",
            "max_length" => "The region cannot exceed 255 characters."
        ]
    ];

    public function getDetailValidationRules(): array
    {
        return $this->validationRules;
    }

    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }
    /**
     * Fetches details of the products associated with a specific user.
     * 
     * @param int       $user_id The ID of the user whose products are to be fetched.
     * @return array    The list of product details, including the number of backlog items, user stories, and the last updated date. Returns an empty array if no records are found.
     * @author          Ajmal Akram S
     */
    public function getUsersProductDetails($user_id)
    {
        $sql = "SELECT 
                    p.external_project_id AS id,
                    p.product_name AS name
                FROM 
                    scrum_product p
                INNER JOIN 
                    scrum_product_user pu ON pu.r_product_id = p.external_project_id AND pu.is_deleted = 'N'
                LEFT JOIN 
                    scrum_backlog_item bi ON bi.r_product_id = pu.r_product_id AND bi.is_deleted = 'N'
                LEFT JOIN 
                    scrum_epic e ON e.r_backlog_item_id = bi.backlog_item_id AND e.is_deleted = 'N'
                LEFT JOIN 
                    scrum_user_story us ON us.r_epic_id = e.epic_id AND us.is_deleted = 'N'
                WHERE 
                    pu.r_user_id = :u_id:
                GROUP BY 
                    pu.r_product_id";
        $query = $this->query($sql, [
            'u_id' => $user_id
        ]);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * Retrieves the backlog items related to a specific sprint.
     * 
     * @param int           $id The sprint ID for which backlog items are to be retrieved.
     * @return array|string The list of backlog items or an empty string if no records are found.
     * @author              Ajmal Akram S
     */
    public function getProductsBacklogs($id)
    {
        $sql = "SELECT DISTINCT
                    bi.backlog_item_id AS id,
                    bi.backlog_item_name AS name
                FROM 
                    scrum_backlog_item AS bi
                JOIN 
                    scrum_epic AS se 
                    ON se.r_backlog_item_id = bi.backlog_item_id
                JOIN 
                    scrum_user_story AS us 
                    ON us.r_epic_id = se.epic_id
                JOIN 
                    scrum_task AS st 
                    ON st.r_user_story_id = us.user_story_id
                JOIN 
                    scrum_sprint_task AS sst 
                    ON sst.r_task_id = st.task_id
                WHERE 
                    sst.r_sprint_id = :p_id:";
        $query = $this->query($sql, [
            'p_id' => $id
        ]);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return '';
    }

    /**
     * Fetches the list of sprints associated with a specific product.
     * 
     * @param int           $id The product ID for which sprints are to be retrieved.
     * @return array|string The list of sprints or an empty string if no records are found.
     * @author              Ajmal Akram S
     */
    public function getProductsSpirints($id)
    {
        $sql = "SELECT
                    sprint_id AS id, 
                    sprint_name AS name
                FROM 
                    scrum_sprint
                WHERE 
                    r_product_id = :p_id:";
        $query = $this->query($sql, [
            'p_id' => $id
        ]);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return '';
    }

    /**
     * Fetches the environment setup data, including project, parent product, and live server URL.
     * @return array|string It returns an array of project details where each project has a parent and a live server URL.
     * @author Deepika sakthivel
     */

    function environmentSetup($product_ids)
    {
        $productIds = array_column($product_ids, 'id');
        // Convert the array to a comma-separated string of integers
        $productIdsString = implode(',', $productIds);
        // SQL query to fetch project data with parent product and live server URL
        $query = "
            SELECT 
                p.external_project_id,
                p.product_name AS Project,
                parent.product_name AS Product, 
                b.server_url AS Server_live_url
            FROM 
                scrum_product p
            LEFT JOIN 
                scrum_product parent 
                ON p.parent_id = parent.external_project_id
            LEFT JOIN 
                branch_url_tracker b 
                ON p.external_project_id = b.r_product_id AND b.environment_type = 'live'
               WHERE p.external_project_id IN ($productIdsString)
        ";
        // Execute the query
        $data = $this->db->query($query)->getResultArray();
        // Return the array
        return $data;
    }

    /**
     * Fetches the environment data, including project and their URL's.
     * 
     * @param int $id This ID is used to retrieve data specific to the project.
     * @return array|string It Returns an array of project details if data is found,
     * 
     * @author Deepika sakthivel
     */
    public function environmentview($id)
    {
        // SQL query to fetch full environment details for a specific project
        $query = "SELECT
                   bucket_name,
                   access_key,
                   secret_key,
                   region,
                   users,
                   environment_type,
                   git_url,
                   jenkins_url,
                   ip_address,
                   server_url,
                   authority,
                   product_name,
                   external_project_id
				FROM 
                    scrum_product AS sp 
                inner JOIN 
                    branch_url_tracker AS but 
                ON  
                    sp.external_project_id = but.r_product_id
                inner join
                    log_details as log
                ON  
                    sp.external_project_id = log.r_product_id
                WHERE
                    but.r_product_id = $id
                GROUP BY 
                    environment_type
                ORDER BY 
                     NULL      
                ";
        // Execute the query
        $data = $this->db->query($query);
        // Return results if data is found, else return nothing
        if ($data->getNumRows() > 0) {
            return $data->getResultArray();
        }
    }

    /**
     * Inserts environment setup data for a specific product into the branch_url_tracker and log_details tables.
     * 
     * @param int $id The product ID for which the environment data and log entry will be inserted.
     * @return bool Returns true on successful insertion, false if there is any error during the process.
     * 
     * @author Deepika sakthivel
     */
    function insertData($id)
    {
        // Define the environment types to insert
        $environments = ['dev', 'test', 'uat', 'prelive', 'live'];
        $query = "INSERT INTO branch_url_tracker(r_product_id, environment_type) VALUES (:r_product_id:, :environment_type:)";
        // Execute the query for each environment
        foreach ($environments as $environment) {
            $this->db->query($query, ['r_product_id' => $id, 'environment_type' => $environment]);
        }
        // Insert into log_details
        $logQuery = "INSERT INTO log_details(r_product_id) VALUES (:r_product_id:)";
        $this->db->query($logQuery, ['r_product_id' => $id]);
        return true;
    }

    /**
     * Updates log details in the log_details table.
     *
     * @param array $data Contains the updated log details, including bucket name, access key, secret key, region, and users.
     *
     * @return bool Returns true if the update query executes successfully.
     * 
     * @author Deepika Sakthivel
     */
    function logdetailsUpdate($data)
    {
        $sql = "UPDATE log_details SET ";
        $params = [];
        if ($data['bucket_name'] !== '********') {
            $sql .= "bucket_name = :bucket_name:, ";
            $params['bucket_name'] = $data['bucket_name'];
        }

        if ($data['access_key'] !== '********') {
            $sql .= "access_key = :access_key:, ";
            $params['access_key'] = $data['access_key'];
        }

        if ($data['secret_key'] !== '********') {
            $sql .= "secret_key = :secret_key:, ";
            $params['secret_key'] = $data['secret_key'];
        }

        // Check if region is different from ********
        if ($data['region'] !== '********') {
            $sql .= "region = :region:, ";
            $params['region'] = $data['region'];
        }

        // Convert the users array to JSON if it's set
        if (!isset($data['users']) || isset($data['users'])) {
            $usersJson = json_encode($data['users']);
            $sql .= "users = :users:, ";
            $params['users'] = $usersJson;
        }

        // Remove the trailing comma
        $sql = rtrim($sql, ", ");

        // Add WHERE clause to target specific log details
        $sql .= " WHERE r_product_id = :id:";
        $params['id'] = $data['id'];

        // Execute the query with parameters
        $result = $this->db->query($sql, $params);

        return true;
    }

    /**
     * Searches for projects based on the provided search query.
     * 
     * @param string $searchQuery The search query used to filter project names.
     * 
     *  @return array Returns an array of projects that match the search criteria.
     *                If no results are found, returns an empty array.
     * 
     * @author Deepika sakthivel
     */
    public function searchProject($searchQuery, $product_ids)
    {
        $productIds = array_column($product_ids, 'id');
        // Convert the array to a comma-separated string of integers
        $productIdsString = implode(',', $productIds);
        // SQL query to search for projects whose names match the search query
        $sql = "SELECT 
                        p.external_project_id,
                p.product_name AS Project,
                parent.product_name AS Product, 
                b.server_url AS Server_live_url
            FROM 
                scrum_product p
            LEFT JOIN 
                scrum_product parent 
                ON p.parent_id = parent.external_project_id
            LEFT JOIN 
                branch_url_tracker b 
                ON p.external_project_id = b.r_product_id AND b.environment_type = 'live'
                WHERE 
                    p.external_project_id IN ($productIdsString) 
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

    /**
     *  Filters the projects based on the provided filter criteria.
     * 
     * @param array $filter The filter criteria containing various possible fields.   
     * 
     *  @return array An array of matching projects with their details like product name, parent product, and server URL.
     * 
     * @author Deepika sakthivel
     */
    public function environmentFilter(array $filter, $product_ids): array
    {
        $productIds = array_column($product_ids, 'id');
        // Convert the array to a comma-separated string of integers
        $productIdsString = implode(',', $productIds);

        // Prepare the initial SQL query
        $sql = "
            SELECT 
                p.external_project_id,
                p.product_name AS Project,
                parent.product_name AS Product, 
                b.server_url AS Server_live_url
            FROM 
                scrum_product p
            LEFT JOIN 
                scrum_product parent 
                ON p.parent_id = parent.external_project_id
            LEFT JOIN 
                branch_url_tracker b 
                ON p.external_project_id = b.r_product_id
            WHERE 
                p.external_project_id IN ($productIdsString)
        ";

        // Initialize the array to hold bind parameters for the query
        $params = [];

        // Apply filters based on criteria in the $filter array
        if (!empty($filter['product_id'])) {
            $sql .= " AND (";
            $count = 0;
            // Prepare a placeholder for each product_id
            foreach ($filter['product_id'] as $key => $value) {
                if ($count != 0) {
                    $sql .= " OR";
                }
                $sql .= " p.external_project_id = ?";
                $params[] = $value;  // Append the product_id to bind parameters
                $count++;
            }
            $sql .= ")";
        }

        if (!empty($filter['git_url'])) {
            $sql .= " AND b.git_url LIKE ?";
            $params[] = "%" . $filter['git_url'] . "%";  // Bind the git_url parameter
        }

        if (!empty($filter['jenkins_url'])) {
            $sql .= " AND b.jenkins_url LIKE ?";
            $params[] = "%" . $filter['jenkins_url'] . "%";  // Bind the jenkins_url parameter
        }

        if (!empty($filter['ip_url'])) {
            $sql .= " AND b.ip_url LIKE ?";
            $params[] = "%" . $filter['ip_url'] . "%";  // Bind the ip_url parameter
        }

        if (!empty($filter['server_url'])) {
            $sql .= " AND b.server_url LIKE ?";
            $params[] = "%" . $filter['server_url'] . "%";  // Bind the server_url parameter
        }

        // Append the complex EXISTS/NOT EXISTS condition
        $sql .= "
            AND (
                EXISTS (
                    SELECT 1
                    FROM branch_url_tracker b_sub
                    WHERE 
                        b_sub.r_product_id = p.external_project_id
                        AND b_sub.environment_type = 'live'
                        AND b_sub.server_url != ''
                )
                OR NOT EXISTS (
                    SELECT 1
                    FROM branch_url_tracker b_sub
                    WHERE 
                        b_sub.r_product_id = p.external_project_id
                        AND b_sub.environment_type = 'live'
                        AND b_sub.server_url != ''
                )
            )
        ";

        // Finalize query by grouping results by product name
        $sql .= " GROUP BY p.product_name";

        // Execute the query with the parameters
        $query = $this->db->query($sql, $params);

        // Return the result as an array
        return $query->getResultArray();


    }

    public function branchFetchAll($projectId)
    {
        $sql = "  SELECT authority,git_url 
                FROM branch_url_tracker
                WHERE r_product_id = :projectId:
            ";
        $params = ["projectId" => $projectId];
        $query = $this->db->query($sql, $params);
        return $query->getResultArray();
    }

    public function branchFetching($projectId, $environment_type): array
    {
        $sql = "  SELECT authority,git_url 
                FROM branch_url_tracker
                WHERE r_product_id = :projectId:
                AND environment_type = :environment_type:
            ";
        $params = ["projectId" => $projectId, "environment_type" => $environment_type];
        $query = $this->db->query($sql, $params);
        return $query->getResultArray();
    }

    public function existenceOfProjectCheck($projectId)
    {
        $sql = "  SELECT * from release_management_operation_status
                WHERE  r_product_id = :project_id:
            ";
        $query = $this->db->query($sql, ["project_id" => $projectId]);
        return $query->getResultArray();
    }

    public function insertStatus($message, $projectId)
    {
        $sql = "  INSERT INTO release_management_operation_status 
                ( r_product_id,status)
                VALUES (?,?)
            ";
        $query = $this->db->query($sql, [$projectId, $message['status']]);
        return $query;
    }

    public function updateStatus($message, $projectId)
    {
        $sql = "  UPDATE release_management_operation_status
                SET  status = :operation:
                WHERE  r_product_id = :project_id:
            ";
        $query = $this->db->query($sql, ["operation" => $message['status'], "project_id" => $projectId]);
        return $query;
    }


    /**
     *  Fetches the commit history for a specific product, sprint, and backlog item.
     * 
     * @param int $productId The ID of the product.
     * @param int $sprintId The ID of the sprint.
     * @param int $backlogId The ID of the backlog item.
     * 
     * @return array Returns an array of commit history records. If no records are found, an empty array is returned.
     * 
     * @author Deepika sakthivel
     */
    public function commitHistoryFetch($productId, $sprintId, $backlogId)
    {
        $sql = "
            SELECT 
                author,
                product_name,
                sprint_name,
                backlog_item_name,
                no_of_files,
                CONCAT(from_branch, ' -> ', to_branch) AS branch_transition,
                CONCAT(from_commit_id, ' -> ', to_commit_id) AS commit_transition,
                commit_time
            FROM 
                scrum_product sp
            JOIN 
                commit_history 
                ON external_project_id = r_product_id
            JOIN 
                scrum_sprint 
                ON sprint_id = r_sprint_id
            JOIN 
                scrum_backlog_item 
                ON backlog_item_id = r_backlog_item_id
            WHERE 
                external_project_id = :r_product_id: 
                AND sprint_id = :r_sprint_id: 
                AND backlog_item_id = :r_backlog_item_id: 
                AND DATE(commit_time) = CURDATE()
        ";

        // Execute the query
        $result = $this->db->query($sql, [
            'r_product_id' => $productId,
            'r_sprint_id' => $sprintId,
            'r_backlog_item_id' => $backlogId
        ]);
        // Return the results as an associative array
        return $result->getResultArray();
    }

    /**
     * Fetches the author for a specific employee based on their employee ID.
     * 
     * @param int $employee_id The employee ID (external employee ID).
     * @return string|null Returns the full name of the employee if found, otherwise null.
     * 
     * @author Deepika Sakthivel
     */
    public function fetchAuthor($employee_id)
    {
        $sql = "SELECT 
                    CONCAT(user.first_name, ' ', user.last_name) AS Author
                from
                    scrum_user user
                WHERE 
                    user.external_employee_id = :employee_id:";
        // Execute the query
        $result = $this->db->query($sql, ['employee_id' => $employee_id]);

        return $result->getResultArray();
    }

    /**
     * Inserts a new record into the commit_history table with the provided commit data.
     * 
     * @param array $data The commit data, including author, product ID, sprint ID, backlog ID, and commit details
     * @return bool|int Returns true if the insert was successful, or false if there was an error.
     * 
     * @author Deepika Sakthivel
     */
    public function commitHistoryInsert($data)
    {
        // print_r($data);
        // die;
        $sql = "INSERT INTO commit_history(author,r_product_id,r_sprint_id,r_backlog_item_id,no_of_files,from_branch,from_commit_id,to_branch,to_commit_id)
                 VALUES (:author:,:r_product_id:,:r_sprint_id:,:r_backlog_id:,:no_of_files:,:from_branch:,:from_commit_id:,:to_branch:,:to_commit_id:)";

        // Execute the insert query
        $result = $this->db->query($sql, [
            'author' => $data['author'],
            'r_product_id' => $data['product_id'],
            'r_sprint_id' => $data['sprint_id'],
            'r_backlog_id' => $data['backlog_id'],
            'no_of_files' => $data['no_of_files'],
            'from_branch' => $data['from_branch'],
            'from_commit_id' => $data['from_commit_id'],
            'to_branch' => $data['to_branch'],
            'to_commit_id' => $data['to_commit_id']
        ]);
        return $result;
    }

    /**
     * Filters commit history based on multiple criteria.
     * 
     * @param array $filter Filter conditions (e.g., author, product name, date range).
     * @param int $productId Product ID for which history is to be fetched.
     * @param int $sprintId Sprint ID associated with the commit history.
     * @param int $backlogId Backlog ID related to the commit history.
     * @return array An array of filtered commit history results.
     * 
     * @author Deepika Sakthivel
     */
    public function commithistoryFilter($filter, $productId, $sprintId, $backlogId): array
    {
        // Base SQL query with initial join statements
        $sql = "
            SELECT 
                author,
                no_of_files,
                CONCAT(from_branch, ' -> ', to_branch) AS branch_transition,
                CONCAT(from_commit_id, ' -> ', to_commit_id) AS commit_transition,
                commit_time
            FROM 
                scrum_product
            join
                commit_history
                on external_project_id=r_product_id
            join
                scrum_sprint
                on sprint_id=r_sprint_id
            join
                scrum_backlog_item
                on backlog_item_id=r_backlog_item_id
                where external_project_id=:r_product_id: and sprint_id=:r_sprint_id: and backlog_item_id=:r_backlog_item_id:
                ";

        // Array to hold bind parameters for the query
        $params = [];
        $params = ["r_product_id" => $productId, "r_sprint_id" => $sprintId, "r_backlog_item_id" => $backlogId];
        if (!empty($filter['author'])) {
            $sql .= " AND author LIKE :author:";
            $params['author'] = "%" . $filter['author'] . "%";
        }
        if (!empty($filter['product_name'])) {
            $sql .= " AND product_name LIKE :product_name:";
            $params['product_name'] = "%" . $filter['product_name'] . "%";
        }
        if (!empty($filter['sprint_name'])) {
            $sql .= " AND sprint_name LIKE :sprint_name:";
            $params['sprint_name'] = "%" . $filter['sprint_name'] . "%";
        }
        if (!empty($filter['backlog_item_name'])) {
            $sql .= " AND backlog_item_name LIKE :backlog_item_name:";
            $params['backlog_item_name'] = "%" . $filter['backlog_item_name'] . "%";
        }
        if (!empty($filter['fromDate']) && !empty($filter['toDate'])) {
            $filter['toDate'] .= ' 23:59:59';
            $sql .= " AND commit_time BETWEEN :fromDate: AND :toDate:";
            $params['fromDate'] = $filter['fromDate'];
            $params['toDate'] = $filter['toDate'];
        }

        // Execute the query with parameters and return results
        $query = $this->db->query($sql, $params);
        return $query->getResultArray();
    }

    // public function insertloghistory($history, $emp_id, $emp_name)
    // {
    //     $sql = "
    //         insert into log_download_history(employee_id,employee_name,issue_id,issue,reason,file,file_size,file_created_date,folder)
    //         values(:emp_id:,:emp_name:,:issue_id:,:issue:,:reason:,:file:,:filesize:,:createddate:,:folder:)";

    //     $result = $this->db->query($sql, [
    //         'emp_id' => $emp_id,
    //         'emp_name' => $emp_name,
    //         'issue_id' => $history['issueid'],
    //         'issue' => $history['issue'],
    //         'reason' => $history['reason'],
    //         'file' => $history['filename'],
    //         'folder' => $history['folder'],
    //         'filesize' => $history['filesize'],
    //         'createddate' => $history['createddate'],
    //     ]);
    //     return true;

    // }

    public function productIdFetch($productName): array
    {
        $sql = " SELECT product_id from 
                 scrum_product as sp
                 where product_name LIKE \"%:product_name:%\"  
               ";
        $query = $this->db->query($sql, ['product_name' => $productName]);
        return $query->getResultArray();
    }

    public function loghistoryFetch($filename)
    {
        $sql = "SELECT distinct  folder, file FROM log_download_history WHERE file LIKE ?";
        $data = $this->db->query($sql, ['%' . $filename . '%'])->getResultArray();
        return $data;
    }

    /**
     * Fetches all records from the log_download_history table.
     * 
     * @return array An array of log download history records.
     * 
     * @author Deepika Sakthivel
     */
    public function logdownloadFetch()
    {
        $sql = "SELECT employee_name,issue_id,issue,reason,folder,file,log_download_date from log_download_history";
        $data = $this->db->query($sql)->getResultArray();
        return $data;
    }

    /**
     * Fetches filtered records from the log_download_history table based on given criteria.
     *
     * @param array $filter An associative array of filter criteria.
     * @return array The filtered records from the log_download_history table.
     * 
     * @author Deepika Sakthivel
     */
    public function logdownloadFilter($filter): array
    {
        // Base SQL query with initial join statements
        $sql = "SELECT employee_name,issue_id,issue,reason,folder,file,log_download_date FROM log_download_history WHERE 1=1";

        // Array to hold bind parameters for the query
        $params = [];

        // Apply filters based on criteria in the $filter array
        if (!empty($filter['employee_name'])) {
            $sql .= " AND employee_name LIKE ?";
            $params[] = "%" . $filter['employee_name'] . "%";
        }

        if (!empty($filter['issue_id'])) {
            $sql .= " AND issue_id LIKE ?";
            $params[] = "%" . $filter['issue_id'] . "%";
        }

        if (!empty($filter['fromDate']) && !empty($filter['toDate'])) {
            $filter['toDate'] .= ' 23:59:59';
            $sql .= " AND log_download_date BETWEEN ? AND ?";
            $params[] = $filter['fromDate'];
            $params[] = $filter['toDate'];
        }
        // Execute the query with all the parameters
        $query = $this->db->query($sql, $params);

        // Return the result as an array
        return $query->getResultArray();
    }

    /**
     * Fetches a  report of log download history, grouped by files.
     *
     * @return array Returns an array of aggregated log download history data.
     * 
     * @author Deepika Sakthivel
     */
    public function logdownloadReport()
    {
        $sql = "SELECT 
                folder,
                file,
                count(file) as Total_downloads,
                GROUP_CONCAT(DISTINCT issue_id ORDER BY issue_id ASC) AS issue_id,
                GROUP_CONCAT(DISTINCT employee_name ORDER BY employee_name ASC) AS employee_name,
                MAX(file_size) AS file_size, 
                MAX(file_created_date) AS file_created_date  
            FROM 
                log_download_history
            GROUP BY 
                folder;
            ";
        $data = $this->db->query($sql)->getResultArray();
        return $data;
    }

    public function logdetailsdata($project_id)
    {
        $sql = "SELECT * from log_details where r_product_id=:projectid:";

        $query = $this->db->query($sql, ["projectid" => $project_id])->getResultArray();
        return $query;
    }
    public function loguserdata($ex_emp_id)
    {
        $sql = 'SELECT r_product_id FROM log_details WHERE users LIKE :emp_id:';

        // Add the wildcards directly in the bound parameter
        $query = $this->db->query($sql, ["emp_id" => "%$ex_emp_id%"])->getResultArray();

        return $query;

    }

}
?>