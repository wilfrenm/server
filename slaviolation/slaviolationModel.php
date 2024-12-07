<?php

/**
 * SLA Policy Model
 *
 * Purpose: Retrieves data regarding SLA violations for live issues.
 *
 * Authors: Suriya, Naveen Kumar, Vaidhegi
 * Created Date: 2024-10-31
 */

namespace App\Models\slaPolicy;

use CodeIgniter\Model;
use Config\Database;
use App\Models\BaseModel;
use PDO;

/**
 * @author Vaidhegi
 * Description : These functions retrieve all data from Redmine, insert it into the Scrum Tool, and update the current data from Redmine
 *
 * Purpose : Trigger an email notification upon SLA violation 
 */
class SlaViolationModel extends BaseModel
{
    protected $table = 'scrum_tool_2_0';

    public function insertSlaViolationDetails($insertData)
    {

        $sql = "INSERT INTO sla_violation_report (
                        r_issue_id,
                        r_priority_id,
                        issue_description,
                        r_product_id,
                        r_project_id,
                        issue_created_date,
                        expected_close_time,
                        actual_close_time,
                        issue_status,
                        created_date,
                        updated_date,
                        assigned_to_id,
                        violation_remainder
                        
                    )VALUES (
                        :r_issue_id:,
                        :r_priority_id:,
                        :issue_description:,
                        :r_product_id:,
                        :r_project_id:,
                        :issue_created_date:,
                        :expected_close_time:,
                        :actual_close_time:,
                        :issue_status:,
                        :created_date:,
                        :updated_date:,
                        :assigned_to_id:,
                        :violation_remainder:

                    )";

        $query = $this->db->query($sql, [
            'r_issue_id' => $insertData['issue_id'],
            'r_priority_id' => $insertData['priority_id'],
            'issue_description' => $insertData['issue_description'],
            'r_product_id' => $insertData['product_id'],
            'r_project_id' => $insertData['project_id'],
            'issue_created_date' => $insertData['issue_created_date'],
            'expected_close_time' => $insertData['expected_close_time'],
            'actual_close_time' => $insertData['closed_date_time'],
            'issue_status' => $insertData['status'],
            'created_date' => date('Y-m-d H:i:s'), // Set current date and time
            'updated_date' => date('Y-m-d H:i:s'),
            'assigned_to_id' => $insertData['assigned_to_id'],
            'violation_remainder' => $insertData['violation_remainder']

        ]);

    }

    /**
     * @author Suriya
     * @return array
     * Purpose: To get Holiday for finding estimation close time
     */
    public function GetHolidayDate()
    {
        $sql = "SELECT
                    holiday_start_date
                From
                    scrum_holidays
                Where 
                    1
                    ";
        $query = $this->db->query($sql, []);
        if ($query->getNumRows() > 0) {
            return $HolidayList = $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Suriya
     * @return array
     * Purpose: to get the SLA time 
     */
    function CalculateSlaTime()
    {
        $sql = "SELECT 
                    customer_id,
                    category_id,
                    sla_time

                FROM
                    sla_policy_matrix
                WHERE 
                    status = 1
                    ";

        $query = $this->db->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Suriya
     * @return array
     * Purpose: To get product details for filters
     */
    public function getProduct($userProjectId)
    {

        $sql = "SELECT 
                external_project_id as product_id,
                product_name AS product_name
                FROM scrum_product AS product
                WHERE 1 ";

        //Product Name Filter
        if (!empty($userProjectId)) {
            $data = is_array($userProjectId) ? $userProjectId : explode(',', $userProjectId);
            $data = implode(',', array_map(fn($item) => $this->db->escape($item), $data));
            $sql .= " AND  external_project_id IN ($data)";
        }

        $query = $this->db->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Suriya
     * @return array
     * Purpose: To get customer details for filters
     */

    public function getCustomer($userProjectId)
    {

        $sql = "SELECT product.external_project_id AS customer_id,
                product_name AS customer_name 
                FROM 
                    scrum_product AS product
                WHERE 1";

        //Product Name Filter
        if (!empty($userProjectId)) {
            $data = is_array($userProjectId) ? $userProjectId : explode(',', $userProjectId);
            $data = implode(',', array_map(fn($item) => $this->db->escape($item), $data));
            $sql .= " AND  product.parent_id in($data)";
        }

        $query = $this->db->query($sql);

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Suriya
     * @return array
     * Purpose: To get Issue details for filters
     */
    public function getIssueStatus()
    {
        $sql = "SELECT 
        id AS Status_id,
        name AS Issuse_status
        from scrum_task_status";
        $query = $this->db->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Suriya
     * @return array
     * Purpose: To get Priority details for filters
     */

    public function getPriority()
    {
        $sql = "SELECT 
                    category_id ,
                    category_name
                FROM 
                    sla_policy_category";
        $query = $this->db->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Suriya
     * @return array
     * Purpose: To get data to render the report page data
     */

    public function getFilterIssuseData($filter)
    {

        // Initialize the base query
        $select = " ";
        $joins = " ";
        $where = " ";
        $where .= " WHERE 1 ";
        $Order = " ";
        $Order .= "  ORDER BY Violation.issue_created_date DESC  ";

        $sql = "SELECT 
                Violation.r_issue_id AS issue_id,
                Violation.issue_description AS issue_description,
                IF(sc.customer_name != '', (CONCAT(Project.product_name, ' - ', sc.customer_name)), Project.product_name) AS project_Name, 
                Violation.issue_created_date AS created_Date,
                priority.category_name AS priority,
                Violation.expected_close_time AS expected_close_time,
                IssueStatus.name AS status,
                Violation.r_issue_id AS Track_Issue,
                Product.product_name AS Product_Name,
                Violation.actual_close_time AS close_Time,
                Violation.violation_status AS violation_Status,
                COUNT(Violation.r_issue_id) OVER() AS totalIssueCount,
                SUM(CASE WHEN Violation.violation_status = 'Violated' THEN 1 ELSE 0 END) OVER() AS violatedIssueCount,
                SUM(CASE WHEN Violation.violation_status = 'Not violated' THEN 1 ELSE 0 END) OVER() AS notViolatedIssueCount,
                SUM(CASE WHEN Violation.issue_status=5  THEN 1 ELSE 0 END) OVER() AS closedIssueCount
    
    FROM 
        sla_violation_report AS Violation
    INNER JOIN 
        sla_policy_category AS priority
    ON 
        Violation.r_priority_id = priority.category_id
    LEFt JOIN 
        scrum_product AS Product
    ON 
        Violation.r_product_id = Product.external_project_id
    INNER JOIN 
        scrum_product AS Project
    ON 
        Violation.r_project_id = Project.external_project_id 

     LEFT JOIN scrum_customer AS sc 
    ON  
        Violation.r_project_id = sc.customer_id 
    INNER JOIN 
        scrum_task_status AS IssueStatus
    ON Violation.issue_status = IssueStatus.id " . $where . "";

        $where = '';

        $params = [];
        // Violated issue Filter
        if (!empty($filter['violatedIssue'])) {
            $sql .= " AND Violation.violation_status = :violatedIssue: ";
            $bindParam = ["violatedIssue" => "Violated"];
            $params = array_merge($params, $bindParam);
        }
        //Not  Violated issue Filter
        if (!empty($filter['notViolatedIssue'])) {
            $sql .= " AND Violation.violation_status = :notViolatedIssue: ";
            $bindParam = ["notViolatedIssue" => "Not violated"];
            $params = array_merge($params, $bindParam);
        }

        // Closed issue Filter
        if (!empty($filter['closedIssue'])) {
            $sql .= " AND Violation.issue_status = :closedIssue: ";
            $bindParam = ['closedIssue' => 5];
            $params = array_merge($params, $bindParam);
        }

        //Date Filter
        if (!empty($filter['fromDate']) || !empty($filter['toDate'])) {
            $sql .= "AND  Violation.issue_created_date  BETWEEN :fromDate:  AND  :toDate: ";
            $bindParam = ["fromDate" => (string) $filter['fromDate'], "toDate" => (string) $filter['toDate']];
            $params = array_merge($params, $bindParam);
        }

        //Product Name Filter
        if (!empty($filter['defaultproductName'])) {
            $data = is_array($filter['defaultproductName']) ? $filter['defaultproductName'] : explode(',', $filter['defaultproductName']);
            $data = implode(',', array_map(fn($item) => $this->db->escape($item), $data));
            $sql .= " AND  Violation.r_product_id in($data)";
        }

        if (!empty($filter['productName'])) {
            // Convert input to an array if it's not already
            $data = is_array($filter['productName']) ? $filter['productName'] : explode(',', $filter['productName']);

            // Construct conditions for each product name
            $conditions = array_map(function ($value) {
                $value = trim($value); // Trim whitespace
                if (!empty($value)) {
                    return "Violation.r_product_id = " . $this->db->escape($value);
                }
            }, $data);

            // Remove any null or empty conditions
            $conditions = array_filter($conditions);

            // Combine conditions using AND
            if (!empty($conditions)) {
                $sql .= " AND (" . implode(' AND ', $conditions) . ")";
            }
        }

        //Customer Name Filter
        if (!empty($filter['customerName'])) {
            $data = is_array($filter['customerName']) ? $filter['customerName'] : explode(',', $filter['customerName']);
            $data = implode(',', array_map(fn($item) => $this->db->escape($item), $data));
            $sql .= " AND Violation.r_project_id in($data)";
        }

        //Priority Filter
        if (!empty($filter['Priority'])) {
            $data = is_array($filter['Priority']) ? $filter['Priority'] : explode(',', $filter['Priority']);
            $data = implode(',', array_map(fn($item) => $this->db->escape($item), $data));
            $sql .= " AND Violation.r_priority_id in($data)";
        }

        // Issue Status Filter
        if (!empty($filter['issuestatus'])) {
            $data = is_array($filter['issuestatus']) ? $filter['issuestatus'] : explode(',', $filter['issuestatus']);
            $data = implode(',', array_map(fn($item) => $this->db->escape($item), $data));
            $sql .= " AND Violation.issue_status  in($data)";
        }

        // Search Filter
        if (!empty($filter['searchIssue'])) {
            $sql .= ' AND (Violation.r_issue_id LIKE :search:)';
            $bindParam['search'] = trim($filter['searchIssue']) . '%';
            $params = array_merge($params, $bindParam);
        }

        $sql .= $where . $Order;

        if (!empty($filter['sort'])) {
            $sql .= ',' . $filter['sort'];
        }

        $query = $this->db->query($sql, $params);

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }

        return [];
    }

    /**
     * @author Vaidhegi
     * Purpose: This function is used to update the violated status as not violated
     */
    public function updateNotViolated()
    {
        $sql = "UPDATE 
                    sla_violation_report
                SET 
                    violation_status = 'Not violated'
                WHERE 
                    (actual_close_time IS NOT NULL 
                    AND actual_close_time < expected_close_time)  
                    AND (violation_status IS NULL )";

        $query = $this->db->query($sql, []);
    }

    /**
     * @author Vaidhegi
     * @return array
     * Purpose: This function is used to retrieve violation details and their IDs from the Scrum tool's data for the last 15 minutes.
     */
    public function getViolatedIssueId()
    {
        $sql = "SELECT 
                    r_issue_id ,
                    sla_violation_report.expected_close_time,
                    sla_violation_report.actual_close_time,
                    sla_violation_report.assigned_to_id,
                    scrum_user.email_id as assignee_email,
                    sla_violation_report.r_product_id as r_product_id,
                    scrum_product.product_id as product_id,
                    scrum_product.product_name,
                    sla_violation_report.issue_description,
                    sla_violation_report.r_priority_id,
                    sla_policy_category.category_name  
                FROM 
                    sla_violation_report
                LEFT JOIN scrum_user ON 
                    sla_violation_report.assigned_to_id = scrum_user.external_employee_id
                LEFT JOIN scrum_product on 
                    sla_violation_report.r_product_id=scrum_product.product_id  
                LEFT JOIN sla_policy_category on 
                    sla_violation_report.r_priority_id=sla_policy_category.category_id       
                    WHERE 
                (
                    (actual_close_time IS NULL 
                
                AND 
                    expected_close_time BETWEEN DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND NOW())
                OR
                    (actual_close_time IS NOT NULL 
                AND 
                    actual_close_time BETWEEN DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND NOW()
                AND
                    actual_close_time > expected_close_time)
                )";

        $query = $this->db->query($sql, []);

        if ($query->getResultArray() > 0) {
            return $query->getResultArray();
        }
    }

    /**
     * @author Vaidhegi
     * Purpose: This function is used to update the Violated status as Violated
     */
    public function updateViolated($issue_id)
    {
        $sql = "UPDATE 
                    sla_violation_report
                SET 
                    violation_status = 'Violated'
                WHERE 
                    r_issue_id = :r_issue_id:
                    AND violation_status IS NULL 
                    AND 
                    (
                    (actual_close_time IS NULL
                    AND expected_close_time < NOW())
                    OR
                    (actual_close_time IS NOT NULL
                    AND actual_close_time > expected_close_time)
                    )";

        $query = $this->db->query($sql, ['r_issue_id' => $issue_id]);

    }

    /**
     * @author Vaidhegi
     * Purpose: This function updates data from Redmine to the Scrum tool database, specifically for records where the status has been updated within the last 15 minutes.
     */
    public function getStatusUpated($issueStatus)
    {
        $sql = "UPDATE  
                    sla_violation_report
                SET 
                    issue_status = :status_id:,
                    actual_close_time = :closed_on:,
                    r_project_id = :project_id:,
                    r_product_id = :product_id:,
                    issue_description = :issue_name:,
                    assigned_to_id =:assigned_id:,
                    r_priority_id=:priority_id:
                WHERE 
                    r_issue_id = :issue_id:";

        $query = $this->db->query($sql, $issueStatus);
    }

    /**
     * @author Vaidhegi
     * Purpose: This function is used to update the email status, indicating whether the email has been sent or not.
     */
    public function updateEmailStatus($SlaViolatedData)
    {
        $sql = "UPDATE 
                    sla_violation_report
                SET 
                    email_status = 'Sended'
                WHERE 
                    r_issue_id = :r_issue_id:";

        $query = $this->db->query($sql, $SlaViolatedData);
    }

    /**
     * @author Vaidhegi
     * Purpose: This function is used to send an alert email when 50% of the assigned issues have been completed.
     */
    public function getRemaiderHalfTime()
    {
        $sql = "SELECT 
                    r_issue_id ,
                    sla_violation_report.actual_close_time,
                    sla_violation_report.assigned_to_id,
                    scrum_user.email_id,
                    sla_violation_report.r_product_id as r_product_id,
                    scrum_product.product_id as product_id,
                    scrum_product.product_name,
                    scrum_user.first_name,
                    sla_violation_report.issue_description,
                    violation_remainder,
                    sla_violation_report.r_priority_id,
                    sla_policy_category.category_name   
                FROM 
                    sla_violation_report
                LEFT JOIN scrum_user ON 
                    sla_violation_report.assigned_to_id = scrum_user.external_employee_id
                LEFT JOIN scrum_product on 
                    sla_violation_report.r_product_id=scrum_product.product_id
                LEFT JOIN sla_policy_category on 
                    sla_violation_report.r_priority_id=sla_policy_category.category_id    
                WHERE 
                (
                    (actual_close_time IS NULL 
                
                AND 
                    TIMESTAMPDIFF(MINUTE, violation_remainder, NOW()) <= 15)
                )";
        $query = $this->db->query($sql, []);

        if ($query->getResultArray() > 0) {
            return $query->getResultArray();
        }
    }

    /**
     * @author Naveenkumar
     * @return bool
     * Purpose: This function is used to update the violation reason
     */
    public function insertReason($reasonData)
    {
        $sql = "UPDATE 
                    sla_violation_report
                SET
                    violation_reason = :violuation_reson:
                WHERE
                    r_issue_id = :issue_id:";

        $query = $this->db->query($sql, [
            'violuation_reson' => $reasonData['reason'],
            'issue_id' => $reasonData['issue_id']
        ]);
        return $query->getResultArray();
    }

    /**
     * @author Naveenkumar
     * @return array
     * Purpose: This function is used to return the violation reason and status
     */
    public function getViolationReason($trackId)
    {
        $sql = "SELECT 
                    violation_status,
                    violation_reason,
                    expected_close_time 
                FROM 
                    sla_violation_report 
                WHERE 
                    r_issue_id = :issue_id:";

        $query = $this->db->query($sql, ['issue_id' => $trackId]);

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
    }

    /**
     * @author Vaidhegi
     * Purpose: This function is used to track the time spent on project tasks.
     */
    public function getSpentOnProjects($data, $dateRange, $pId)
    {
        $sql = "SELECT
        svr.r_project_id,
        SUM(CASE WHEN svr.violation_status = 'Violated' THEN 1 ELSE 0 END) AS violated_count,
        SUM(CASE WHEN svr.violation_status = 'Not violated' THEN 1 ELSE 0 END) AS not_violated_count,
        COUNT(*) AS total_issue_count,
        SUM(CASE WHEN svr.r_priority_id = 3 THEN 1 ELSE 0 END) AS high_priority_count,
        SUM(CASE WHEN svr.r_priority_id = 1 THEN 1 ELSE 0 END) AS low_priority_count,
        SUM(CASE WHEN svr.r_priority_id = 2 THEN 1 ELSE 0 END) AS medium_priority_count,
        SUM(CASE WHEN svr.r_priority_id = 5 THEN 1 ELSE 0 END) AS critical_priority_count,
        SUM(CASE WHEN svr.r_priority_id = 4 THEN 1 ELSE 0 END) AS urgent_priority_count
    FROM
        sla_violation_report AS svr
    INNER JOIN
        scrum_product AS sp
    ON
        svr.r_project_id = sp.external_project_id
    WHERE 
        svr.r_project_id =:project_id:
        AND sp.parent_id = $pId
        AND svr.issue_created_date BETWEEN '" . $dateRange['start_date'] . "' AND '" . $dateRange['end_date'] . "'
    GROUP BY
        svr.r_project_id";

        $query = $this->db->query($sql, ($data));

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
    }

    /**
     * @author Naveenkumar
     * @return array
     * Purpose: This function is used to return the closed violated issue details
     */
    public function getClosedViolatedSlaMailReport($dateRange, $pId)
    {
        $sql = "SELECT
                    sp.external_project_id AS project_id,
                    sp.product_name AS project_name,
                    spc.category_name AS category,
                    count(spc.category_name) AS issue_count,
                    sts.name AS issue_status
                FROM
                    scrum_product AS sp
                INNER JOIN
                    sla_violation_report AS svr ON sp.external_project_id=svr.r_project_id
                INNER JOIN
                    sla_policy_category AS spc ON svr.r_priority_id=spc.category_id
                INNER JOIN
                    scrum_task_status AS sts ON svr.issue_status=sts.id
                WHERE
                    svr.issue_created_date BETWEEN '" . $dateRange['start_date'] . "' AND '" . $dateRange['end_date'] . "'
                    AND
                    sts.name ='Closed'
                    AND
                    svr.violation_status = 'Violated'
                ";
        if (!empty($pId)) {
            $sql .= " AND sp.parent_id = :parent_id:";
        }
        $sql .= " GROUP BY sp.product_name, spc.category_name";

        $query = $this->db->query($sql, ['parent_id' => $pId]);
        return $query->getResultArray();
    }

    /**
     * @author Naveenkumar
     * @return array
     * Purpose: This function is used to return the closed Not violated issue details
     */
    public function getClosedNotViolatedSlaMailReport($dateRange, $pId)
    {
        $sql = "SELECT
                    sp.external_project_id AS project_id,
                    sp.product_name AS project_name,
                    spc.category_name AS category,
                    count(spc.category_name) AS issue_count,
                    sts.name AS issue_status
                FROM
                    scrum_product AS sp
                INNER JOIN
                    sla_violation_report AS svr ON sp.external_project_id=svr.r_project_id
                INNER JOIN
                    sla_policy_category AS spc ON svr.r_priority_id=spc.category_id
                INNER JOIN
                    scrum_task_status AS sts ON svr.issue_status=sts.id
                WHERE
                    svr.issue_created_date BETWEEN '" . $dateRange['start_date'] . "' AND '" . $dateRange['end_date'] . "'
                    AND
                    sts.name ='Closed'
                    AND
                    svr.violation_status = 'Not violated'
                ";
        if (!empty($pId)) {
            $sql .= " AND sp.parent_id = :parent_id:";
        }
        $sql .= " GROUP BY sp.product_name, spc.category_name";

        $query = $this->db->query($sql, ['parent_id' => $pId]);
        return $query->getResultArray();
    }

    /**
     * @author Naveenkumar
     * @return array
     * Purpose: This function is used to return the pending violated issue details
     */
    public function getPendingViolatedSlaMailReport($dateRange, $pId)
    {
        $sql = "SELECT
                    sp.external_project_id AS project_id,
                    sp.product_name AS project_name,
                    spc.category_name AS category,
                    count(spc.category_name) AS issue_count,
                    sts.name AS issue_status
                FROM
                    scrum_product AS sp
                INNER JOIN
                    sla_violation_report AS svr ON sp.external_project_id=svr.r_project_id
                INNER JOIN
                    sla_policy_category AS spc ON svr.r_priority_id=spc.category_id
                INNER JOIN
                    scrum_task_status AS sts ON svr.issue_status=sts.id
                WHERE
                    svr.issue_created_date BETWEEN '" . $dateRange['start_date'] . "' AND '" . $dateRange['end_date'] . "'
                    AND
                    sts.name !='Closed'
                    AND
                    svr.violation_status = 'Violated'
                ";
        if (!empty($pId)) {
            $sql .= " AND sp.parent_id = :parent_id:";
        }
        $sql .= " GROUP BY sp.product_name, spc.category_name";

        $query = $this->db->query($sql, ['parent_id' => $pId]);
        return $query->getResultArray();
    }

    /**
     * @author Naveenkumar
     * @return array
     * Purpose: This function is used to return the pending Not violated issue details
     */
    public function getPendingNotViolatedSlaMailReport($dateRange, $pId)
    {
        $sql = "SELECT
                    sp.external_project_id AS project_id,
                    sp.product_name AS project_name,
                    spc.category_name AS category,
                    count(spc.category_name) AS issue_count,
                    sts.name AS issue_status
                FROM
                    scrum_product AS sp
                INNER JOIN
                    sla_violation_report AS svr ON sp.external_project_id=svr.r_project_id
                INNER JOIN
                    sla_policy_category AS spc ON svr.r_priority_id=spc.category_id
                INNER JOIN
                    scrum_task_status AS sts ON svr.issue_status=sts.id
                WHERE
                    svr.issue_created_date BETWEEN '" . $dateRange['start_date'] . "' AND '" . $dateRange['end_date'] . "'
                    AND
                    sts.name !='Closed'
                    AND
                    svr.violation_status = 'Not violated'
                ";
        if (!empty($pId)) {
            $sql .= " AND sp.parent_id = :parent_id:";
        }
        $sql .= " GROUP BY sp.product_name, spc.category_name";

        $query = $this->db->query($sql, ['parent_id' => $pId]);
        return $query->getResultArray();
    }
    public function remindHighLevelIssue()
    {
        $sql = "SELECT 
                    r_issue_id,
                    r_priority_id,
                    sla_violation_report.actual_close_time,
                    sla_violation_report.assigned_to_id,
                    scrum_user.email_id,
                    sla_violation_report.r_product_id AS r_product_id,
                    scrum_product.product_id AS product_id,
                    scrum_product.product_name,
                    scrum_user.first_name,
                    sla_violation_report.issue_description,
                    violation_remainder,
                    sla_policy_category.category_name
                FROM 
                    sla_violation_report
                INNER JOIN scrum_user 
                    ON sla_violation_report.assigned_to_id = scrum_user.external_employee_id
                INNER JOIN scrum_product 
                    ON sla_violation_report.r_product_id = scrum_product.product_id
                INNER JOIN sla_policy_category 
                    ON sla_violation_report.r_priority_id = sla_policy_category.category_id
                WHERE 
                    sla_policy_category.category_name = 'High'
                    AND (
                        (actual_close_time IS NULL 
                        AND TIMESTAMPDIFF(MINUTE, violation_remainder, NOW()) <= 15)
                    )";
    }

    public function getMonthWiseIssueData()
    {
        $sql = "SELECT 
                    DATE_FORMAT(svr.issue_created_date, '%Y-%m') AS issue_month,
                    svr.r_issue_id,
                    sp.product_name,
                    svr.violation_status,
                    COUNT(svr.r_issue_id) AS issue_count
                FROM 
                    sla_violation_report AS svr
                INNER JOIN
                    scrum_product AS sp ON svr.r_product_id = sp.product_id
                GROUP BY 
                    issue_month,
                    sp.product_name,
                    svr.violation_status
                ORDER BY 
                    issue_month DESC
                ";
        $query = $this->db->query($sql);
        return $query->getResultArray();
    }

    public function getproductName()
    {
        $sql = "SELECT 
                    sp1.parent_id AS parent_id,
                    sp2.product_name AS product_name
                FROM 
                    scrum_product sp1
                LEFT JOIN 
                    scrum_product sp2
                ON 
                    sp1.parent_id = sp2.external_project_id
                WHERE 
                    sp1.parent_id IS NOT NULL
                GROUP BY 
                    sp1.parent_id, sp2.product_name
                ORDER BY
                    product_name";

        $query = $this->db->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
    }

    public function triggerCongratsMail()
    {
        $sql = " SELECT DISTINCT 
                    svr.r_project_id,
                    svr.assigned_to_id,
                    su.email_id,
                    scrum_product.product_name
                FROM 
                    sla_violation_report AS svr
                INNER JOIN 
                    scrum_user AS su 
                ON 
                    svr.assigned_to_id = su.external_employee_id
                LEFT JOIN scrum_product 
                ON svr.r_product_id=scrum_product.product_id  
                WHERE 
                    svr.r_project_id NOT IN (
                        SELECT 
                            r_project_id 
                        FROM 
                            sla_violation_report 
                        WHERE 
                            violation_status = 'violated' 
                            AND issue_created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    )
                AND 
                    svr.issue_created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

        $query = $this->db->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
    }
}
