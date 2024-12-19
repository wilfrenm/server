<?php

/**
 * RedmineModel.php
 *
 * @category   Model
 * @author     Rameshkrishnan.M, Bharath.J.R, Mahasri.K, Kavitha.L
 * @created    26-OCT-2024
 * @purpose    query for calculating overallcount of five cards, calculate top 10 employee with highest logged hours, calculate top 10 employee with highest logged hours
 *             top 10 project with highest logged hours, top 10 activity by logged hours, top 10 tasks with the highest logged hours, top 10 employees by the number of projects they have worked on
 *             unlogged employees (employees who haven't logged time in the given date range),  efficient Redmine users based on time entries dates , retrieve user count by project and team within a date range          
 */

namespace App\Models\Redmine;
use CodeIgniter\Model;
use Redmine\Models\RedmineBaseModel;

class RedmineModel extends RedmineBaseModel
{
    /**
     * @author Kavitha.L
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose This query calculates total employee count, average working days, average expected working hours, and average actual worked hours within a specified date range.
     * */
    public function dropdown()
    {
        $sql = "SELECT 
                    distinct(custom_values.value) As Team
                  FROM custom_values
                  INNER JOIN custom_fields
                  on custom_values.custom_field_id=custom_fields.id
                  WHERE custom_fields.name='Team' and custom_values.value !='null'  and custom_values.customized_id !=17 ";
        $query = $this->db->query($sql);
        $results = $query->getResultArray();
        return $results;
    }
    public function OverallCount($fromDate, $toDate)
    {
        $sql = "SELECT 
                    (SELECT COUNT(*) AS emp_count 
                        FROM users where status=1 and id not in :id: ) AS employee_count,
                        working_days,
                        working_days * 8 AS working_hours,
                        ROUND((select sum(hours)/(SELECT COUNT(DISTINCT(user_id)) from time_entries where spent_on BETWEEN :fromDate:  AND :toDate:) from time_entries WHERE spent_on BETWEEN :fromDate:  AND :toDate:)) AS average_worked_hours,
                        ROUND((ROUND((SELECT SUM(hours) / (SELECT COUNT(DISTINCT(user_id)) FROM time_entries WHERE spent_on BETWEEN :fromDate: AND :toDate:) FROM time_entries WHERE spent_on BETWEEN :fromDate: AND :toDate:), 2) / working_days), 2) AS average_logged_hours
                    FROM (
                    SELECT 
                        GREATEST(
                        DATEDIFF(:toDate:, :fromDate:) + 1  -- Total days in the range (inclusive)
                        - (FLOOR((DATEDIFF(:toDate:, :fromDate:) + WEEKDAY(:fromDate:)) / 7) * 2)  -- Subtract weekends (Saturdays and Sundays)
                        - (CASE 
                            WHEN WEEKDAY(:fromDate:) = 5 THEN 2  -- If start date is Saturday, subtract both Saturday and Sunday
                            WHEN WEEKDAY(:fromDate:) = 6 THEN 1  -- If start date is Sunday, subtract Sunday
                            ELSE 0
                        END) 
                        - (CASE 
                            WHEN WEEKDAY(:toDate:)=5 THEN 1
                            WHEN WEEKDAY(:toDate:)=6 THEN 2 
                            ELSE 0
                        END)
                        - (
                            SELECT COUNT(*) 
                            FROM wk_public_holidays 
                            WHERE holiday_date BETWEEN :fromDate: AND :toDate:
                            AND DAYOFWEEK(holiday_date) NOT IN (1, 7)  -- Exclude public holidays on weekends (Sunday=1, Saturday=7)
                        ), 
                        0  -- If the result is negative, return 0 instead
                    ) AS working_days
                    ) AS data
                    WHERE 1=1";

        $query = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'id' => $this->id]);
        $results = $query->getResultArray();
        return ($results);
    }

    /**
     * @author Rameshkrishnan.M, Kavitha.L
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose calculate top 10 employee with highest logged hours
     * **/
    public function logHours($fromDate, $toDate, $employeeId, $dataType, $team)
    {
        $sql = "SELECT 
                        users.firstname AS Name,
                        custom_values.value AS Team,
                        GROUP_CONCAT(DISTINCT CONCAT(projects.name, ' |', project_hours.total_hours, ' hrs') 
                                    ORDER BY projects.name ASC SEPARATOR '#') AS project_names,
                        ROUND(SUM(time_entries.hours), 2) AS Total_Hours
                    FROM users 
                    INNER JOIN time_entries 
                        ON time_entries.user_id = users.id
                    LEFT JOIN custom_values 
                        ON custom_values.customized_id = users.id
                    INNER JOIN custom_fields 
                        ON custom_fields.id = custom_values.custom_field_id 
                        AND custom_fields.name = 'Team'
                    INNER JOIN projects
                        ON time_entries.project_id = projects.id
                    LEFT JOIN enumerations
                        ON time_entries.Activity_id = enumerations.id
                        AND enumerations.type = 'TimeEntryActivity'
                    LEFT JOIN (
                        SELECT project_id, user_id, ROUND(SUM(hours), 2) AS total_hours
                        FROM time_entries
                        WHERE spent_on BETWEEN :fromDate: AND :toDate:
                        GROUP BY project_id, user_id
                    ) AS project_hours
                    ON project_hours.project_id = time_entries.project_id AND project_hours.user_id = time_entries.user_id
                    WHERE time_entries.spent_on BETWEEN :fromDate: AND :toDate: AND users.status = 1";

        if ($team != null) {
            // Apply the team filter
            $sql .= " AND custom_values.value = :team:";
        }

        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND users.parent_id = :employeeId:";
        }

        $sql .= " GROUP BY users.id, Team";
        $sql .= ($dataType === "bottom") ? " ORDER BY Total_Hours ASC" : " ORDER BY Total_Hours DESC";
        $sql .= " LIMIT 10";

        // Pass the team as a parameter to the query
        $query = $this->db->query($sql, [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'employeeId' => $employeeId,
            'team' => $team
        ]);

        $result = $query->getResultArray();
        $data = [];

        foreach ($result as $val) {
            $overall = explode("#", $val["project_names"]);
            $nameAndHrs = [];

            foreach ($overall as $entry) {
                $nameAndHrs[] = explode("|", $entry);
            }

            $extraContent = "<table align='center' width='500px'>";
            $extraContent .= "<tr style='background-color:#d3e3f3;'>
                                    <th><h6>Project Name</h6></th>
                                    <th><h6>Logged Hours</h6></th>
                                  </tr>";

            foreach ($nameAndHrs as $value) {
                $extraContent .= "<tr align='center' style='background-color:white; color:black;'>
                                        <td style='padding:10px;'>" . $value[0] . "</td>
                                        <td style='padding:10px;'>" . ($value[1] ?? "") . "</td>
                                      </tr>";
            }

            $extraContent .= "</table>";

            $data[] = [
                "label" => $val["Name"],
                "y" => (float) $val["Total_Hours"],
                "optional" => $val["Team"],
                "extra" => $extraContent
            ];
        }

        return json_encode($data, JSON_NUMERIC_CHECK);
    }

    /**
     * @author Rameshkrishnan.M, Kavitha.L
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose calculate overall log percentage of employee
     * */

    public function logPercentage($fromDate, $toDate, $employeeId, $team)
    {
        $this->db->query("SET SESSION group_concat_max_len = 4000;");
        $sql = "SELECT
                       CASE
                            WHEN user_totals.hours > user_totals.working_days * 8 THEN 'ABOVE 100%' 
                            WHEN user_totals.hours > user_totals.working_days * 8 * 0.7 AND user_totals.hours <= user_totals.working_days * 8 THEN '100%-70%' 
                            WHEN user_totals.hours > user_totals.working_days * 8 * 0.5 AND user_totals.hours <= user_totals.working_days * 8 * 0.7 THEN '70%-50%' 
                            WHEN user_totals.hours <= user_totals.working_days * 8 * 0.5 THEN 'BELOW 50%' 
                            ELSE 'NULL' 
                        END AS log_percentage,

                        COUNT(DISTINCT user_totals.user_id) AS emp_count,
                        GROUP_CONCAT(DISTINCT CONCAT(u.firstname, ' |', custom_values.value, '| ', ROUND(user_totals.hours, 2)) ORDER BY user_totals.hours DESC) AS employee_names_with_team,
                      MAX(CASE
                            WHEN user_totals.hours > user_totals.working_days * 8 THEN ''
                            WHEN user_totals.hours > user_totals.working_days * 8 * 0.7 AND user_totals.hours <= user_totals.working_days * 8 THEN user_totals.working_days * 8
                            WHEN user_totals.hours > user_totals.working_days * 8 * 0.5 AND user_totals.hours <= user_totals.working_days * 8 * 0.7 THEN user_totals.working_days * 8 * 0.7 
                            WHEN user_totals.hours <= user_totals.working_days * 8 * 0.5 THEN user_totals.working_days * 8 * 0.5  
                            ELSE 0
                        END) AS max_hours_per_case,
                        MIN(CASE
                            WHEN user_totals.hours > user_totals.working_days * 8 THEN user_totals.working_days * 8
                            WHEN user_totals.hours > user_totals.working_days * 8 * 0.7 AND user_totals.hours <= user_totals.working_days * 8 THEN user_totals.working_days * 8 * 0.7 -1
                            WHEN user_totals.hours > user_totals.working_days * 8 * 0.5 AND user_totals.hours <= user_totals.working_days * 8 * 0.7 THEN user_totals.working_days * 8 * 0.5 -1
                            WHEN user_totals.hours <= user_totals.working_days * 8 * 0.5 THEN 01
                            ELSE 0
                        END) AS min_hours_per_case
                    FROM
                        time_entries te
                    JOIN 
                        (SELECT 
                            te.user_id,
                            SUM(te.hours) AS hours,
                             GREATEST(
                                    DATEDIFF(:toDate:, :fromDate:) + 1  -- Total days in the range (inclusive)
                                    - (FLOOR((DATEDIFF(:toDate:, :fromDate:) + WEEKDAY(:fromDate:)) / 7) * 2)  -- Subtract weekends (Saturdays and Sundays)
                                    - (CASE 
                                        WHEN WEEKDAY(:fromDate:) = 5 THEN 2  -- If start date is Saturday, subtract both Saturday and Sunday
                                        WHEN WEEKDAY(:fromDate:) = 6 THEN 1  -- If start date is Sunday, subtract Sunday
                                        ELSE 0
                                    END) 
                                    - (CASE 
                                        WHEN WEEKDAY(:toDate:)=5 THEN 1
                                        WHEN WEEKDAY(:toDate:)=6 THEN 2  -- If end date is Saturday or Sunday, subtract 1 day
                                        ELSE 0
                                    END)
                                    - (
                                        SELECT COUNT(*) 
                                        FROM wk_public_holidays 
                                        WHERE holiday_date BETWEEN :fromDate: AND :toDate:
                                        AND DAYOFWEEK(holiday_date) NOT IN (1, 7)  -- Exclude public holidays on weekends (Sunday=1, Saturday=7)
                                    ), 
                                    0  -- If the result is negative, return 0 instead
                                ) AS working_days
                        FROM 
                            time_entries te
                        WHERE 
                            te.spent_on BETWEEN :fromDate: AND :toDate:  
                        GROUP BY 
                            te.user_id
                        ) AS user_totals ON te.user_id = user_totals.user_id
                    JOIN 
                        users u ON u.id = user_totals.user_id
                    LEFT JOIN custom_values ON custom_values.customized_id = u.id  
                    INNER JOIN custom_fields ON custom_fields.id = custom_values.custom_field_id 
                        AND custom_fields.name = 'Team'";
        if (!in_array($employeeId, $this->id)) {
            $sql .= " WHERE u.parent_id = :employeeId:";
        }

        if ($team != null) {
            // Apply the team filter
            $sql .= " WHERE custom_values.value = :team:";
        }
        $sql .= " AND
                                u.status=1
                            GROUP BY 
                                log_percentage
                            HAVING 
                                log_percentage IS NOT NULL
                            ORDER BY 
                                emp_count DESC;"; // Adding ORDER BY to sort by emp_count

        // Execute query
        $query = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'employeeId' => $employeeId, 'team' => $team]);
        $result = $query->getResultArray();
        $data = [];
        foreach ($result as $row) {
            $empNames = explode(",", $row["employee_names_with_team"]);
            $NameAndTeam = [];

            foreach ($empNames as $value) {
                $NameAndTeam[] = explode("|", $value);
            }

            $tableRows = '';
            foreach ($NameAndTeam as $resultValue) {
                $employeeName = $resultValue[0] ?? "N/A";
                $teamName = $resultValue[1] ?? "N/A";
                $hours = $resultValue[2] ?? "N/A";

                $tableRows .= "<tr>
                                    <td style='text-align:center; color:black; padding: 10px;'>" . $employeeName . "</td>
                                    <td style='text-align:center; color:black; padding: 10px;'>" . $hours . "</td>
                                    <td style='text-align:center; color:black; padding: 10px;'>" . $teamName . "</td>
                                </tr>";
            }
            $data[] = [
                "label" => $row["log_percentage"],
                "y" => (float) $row["emp_count"],
                "optional" => $row["log_percentage"],
                "extra" => "
                        <p>
                            <b>Min:</b> 
                            <span style='color:red;'>" . ((isset($row['min_hours_per_case']) && $row['min_hours_per_case'] > 0) ? $row['min_hours_per_case'] . " hrs" : "-") . "</span> 
                            - 
                            <b>Max:</b> 
                            <span style='color:red;'>" . ((isset($row['max_hours_per_case']) && $row['max_hours_per_case'] > 0) ? $row['max_hours_per_case'] . " hrs" : "-") . "</span>
                        </p>
                        <table align='center' width='600px'>
                            <tr style='background-color:#d3e3f3;'>
                                <th><h5>Employee Name</h5></th>
                                <th><h5>Hours</h5></th>
                                <th><h5>Team</h5></th>
                            </tr>
                            " . $tableRows . "
                        </table>"
            ];
        }

        return json_encode($data, JSON_NUMERIC_CHECK);
    }

    /**
     * @author Rameshkrishnan.M, Bharath.J.R
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose calculate top 10 project with highest logged hours
     * */

    public function topProjects($fromDate, $toDate, $employeeId, $dataType, $team)
    {
        // SQL query to fetch top projects based on logged hours
        $sql = "SELECT
                         p.name AS Project, -- Project name
                         GROUP_CONCAT(DISTINCT CONCAT(u.firstname, '|', user_details.total_hours) 
                             ORDER BY u.firstname ASC SEPARATOR ', ') AS Employee_name, -- Concatenate employee names with their respective total hours
                         GROUP_CONCAT(DISTINCT CONCAT(enumerations.name, '|', activity_hours.total_activity_hours, ' hrs') 
                             ORDER BY enumerations.name ASC SEPARATOR ', ') AS activity, -- Concatenate distinct activities and their total hours
                         COUNT(DISTINCT t.user_id) AS Emp_Worked, -- Count of distinct employees who worked on the project
                         ROUND(SUM(t.hours), 2) AS Hours -- Total hours logged for the project
                     FROM 
                         time_entries t
                     INNER JOIN 
                         projects p ON t.project_id = p.id -- Join with projects table to get project names
                     INNER JOIN 
                         users u ON t.user_id = u.id -- Join with users table to get employee names
                     LEFT JOIN 
                         enumerations ON t.activity_id = enumerations.id -- Join with enumerations for activity details
                         AND enumerations.type = 'TimeEntryActivity'
                     LEFT JOIN custom_values ON custom_values.customized_id = u.id  
                     INNER JOIN custom_fields ON custom_fields.id = custom_values.custom_field_id 
                        AND custom_fields.name = 'Team'
                     LEFT JOIN (
                         -- Subquery to calculate total hours per user per project
                         SELECT 
                             te.user_id, 
                             te.project_id,
                             ROUND(SUM(te.hours), 2) AS total_hours
                         FROM 
                             time_entries te
                         WHERE 
                             te.spent_on BETWEEN :fromDate: AND :toDate:
                         GROUP BY 
                             te.user_id, te.project_id
                     ) AS user_details ON user_details.user_id = t.user_id AND user_details.project_id = t.project_id -- Join for user-specific hours
                     LEFT JOIN (
                         -- Subquery to calculate total hours per activity per project
                         SELECT
                             te.project_id,
                             te.activity_id,
                             ROUND(SUM(te.hours), 2) AS total_activity_hours -- Total hours for each activity
                         FROM
                             time_entries te
                         WHERE
                             te.spent_on BETWEEN :fromDate: AND :toDate:
                         GROUP BY
                             te.project_id, te.activity_id -- Group by project and activity ID
                     ) AS activity_hours ON activity_hours.project_id = t.project_id AND activity_hours.activity_id = t.activity_id -- Join for activity-specific hours
                     WHERE 
                         t.spent_on BETWEEN :fromDate: AND :toDate: AND u.status=1";
        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND u.parent_id = :employeeId:";
        }
        if ($team != null) {
            // Apply the team filter
            $sql .= " AND custom_values.value = :team:";
        }
        $sql .= " GROUP BY p.id, p.name";
        $sql .= ($dataType === "bottom") ? " ORDER BY Hours ASC" : " ORDER BY Hours DESC";
        $sql .= " LIMIT 10";

        // Execute the query with bound parameters for dates and employee ID
        $query = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'employeeId' => $employeeId, 'team' => $team]);

        // Fetch query results and prepare data structure for JSON encoding
        $result = $query->getResultArray();
        $data = [];

        // Process each row to separate employee names and logged hours for each project
        foreach ($result as $row) {
            // Process employees
            $employees = explode(', ', $row["Employee_name"]);
            $employeeRows = [];
            foreach ($employees as $emp) {
                $employeeParts = explode('|', $emp);
                if (count($employeeParts) > 1) {
                    $empName = $employeeParts[0];
                    $loggedHours = rtrim($employeeParts[1], ' logged_hrs)');
                    $employeeRows[] = "<tr>
                                             <td style='padding:10px; color: black; text-align:center;'>{$empName}</td>
                                             <td style='padding:10px; color: black; text-align:center;'>{$loggedHours} hrs</td>
                                         </tr>";
                }
            }

            // Process activities
            $activities = explode(', ', $row['activity']);
            $activityLog = [];
            foreach ($activities as $act) {
                $activityParts = explode('|', $act);
                if (count($activityParts) > 1) {
                    $activityName = $activityParts[0];
                    $activityHours = rtrim($activityParts[1], ' hrs');
                    $activityLog[] = "<tr>
                                             <td style='padding:10px; text-align:center;'>{$activityName}</td>
                                             <td style='padding:10px; text-align:center;'>{$activityHours} hrs</td>
                                         </tr>";
                }
            }

            // Build data structure with project label, hours, and detailed employee table
            $data[] = [
                "label" => $row["Project"],
                "y" => (int) $row["Hours"],
                "optional" => "EMPLOYEE COUNT: " . $row["Emp_Worked"],
                "extra" => "<table align='center' width='500px'>
                                     <tr style='background-color:#d3e3f3;'>
                                         <th><h5>Employee Name</h5></th>
                                         <th><h5>Logged Hours</h5></th>
                                     </tr>
                                     " . implode('', $employeeRows) . "
                                 </table>
                                 <br>
                                 <h2 style='text-align:center; color:black;'>Activities Involved In The Project</h2>
                                 <table align='center' width='500px'>
                                     <tr style='background-color:#d3e3f3;'>
                                         <th><h5>Activity</h5></th>
                                         <th><h5>Activity Hours</h5></th>
                                     </tr>
                                     " . implode('', $activityLog) . "
                                 </table>"
            ];
        }

        // Return final data as JSON object with numeric values
        return json_encode($data, JSON_NUMERIC_CHECK);
    }

    /**
     * @author Rameshkrishnan.M, Bharath.J.R, Kavitha.L
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose top 10 activity by logged hours
     * */

    public function topActivites($fromDate, $toDate, $employeeId, $dataType, $team)
    {
        $this->db->query("SET SESSION group_concat_max_len = 10000;");
        // SQL query to get top activities with employee details and hours spent.
        $sql = "SELECT
                        enumerations.name AS Activity,
                        GROUP_CONCAT(
                            DISTINCT CONCAT(u.firstname, ' |', custom_values.value, '| ', ROUND(emp_hours.total_hours, 2), ' hrs') 
                            ORDER BY u.firstname 
                            SEPARATOR ', '
                        ) AS EmployeeNamesAndTeamHours,
                        COUNT(DISTINCT te.user_id) AS Emp_Count,
                        ROUND(SUM(te.hours), 2) AS Hours
                    FROM 
                        enumerations
                    INNER JOIN 
                        time_entries te ON enumerations.id = te.activity_id
                    INNER JOIN 
                        users u ON te.user_id = u.id
                    LEFT JOIN 
                        custom_values ON custom_values.customized_id = u.id
                    INNER JOIN 
                        custom_fields ON custom_fields.id = custom_values.custom_field_id 
                        AND custom_fields.name = 'Team'
                    INNER JOIN (
                        -- Subquery to calculate total hours per user per activity
                        SELECT 
                            te.user_id,
                            te.activity_id,
                            SUM(te.hours) AS total_hours
                        FROM 
                            time_entries te
                        WHERE 
                            te.spent_on BETWEEN :fromDate: AND :toDate:
                        GROUP BY 
                            te.user_id, te.activity_id
                    ) emp_hours ON emp_hours.user_id = te.user_id AND emp_hours.activity_id = te.activity_id
                    WHERE   
                        enumerations.type = 'TimeEntryActivity'
                        AND te.spent_on BETWEEN :fromDate: AND :toDate:
                        AND u.status = 1";

        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND u.parent_id = :employeeId:";
        }
        if ($team != null) {
            // Apply the team filter
            $sql .= " AND custom_values.value = :team:";
        }
        $sql .= "
                GROUP BY 
                    enumerations.name";

        $sql .= ($dataType === "bottom")
            ? " ORDER BY Hours ASC"
            : " ORDER BY Hours DESC";

        $sql .= " LIMIT 10";

        // Execute the query with bound parameters for dates and employee ID
        $query = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'employeeId' => $employeeId, 'team' => $team]);

        // Fetch results and prepare data array to be returned as JSON
        $result = $query->getResultArray();
        $data = [];

        // Iterate over each result row and format employee details into a table structure
        foreach ($result as $row) {
            // Employee details concatenated as a string in 'EmployeeNamesAndTeamHours'
            $employeeDetails = explode(",", $row["EmployeeNamesAndTeamHours"]);
            $tableRows = '';

            // Iterate through the employee details string to create table rows
            foreach ($employeeDetails as $empDetail) {
                // Split by the '|' separator and ensure we get 3 parts
                $empDetailParts = explode("|", $empDetail);

                // Check if we got exactly 3 parts: empName, teamName, and empHours
                $empName = isset($empDetailParts[0]) ? trim($empDetailParts[0]) : "N/A";
                $teamName = isset($empDetailParts[1]) ? trim($empDetailParts[1]) : "N/A";
                $empHours = isset($empDetailParts[2]) ? trim($empDetailParts[2]) : "N/A";

                $tableRows .= "<tr>
                                        <td style='padding: 10px; color:black; text-align:center;'>$empName</td>
                                        <td style='padding: 10px; color:black; text-align:center;'>$empHours</td>
                                        <td style='padding: 10px; color:black; text-align:center;'>$teamName</td>
                                    </tr>";
            }

            // Prepare data structure for each activity
            $data[] = [
                "label" => $row["Activity"],
                "y" => (int) $row["Hours"],  // Corrected to Hours, as it's calculated for each activity
                "optional" => "EMPLOYEE COUNT: " . $row["Emp_Count"],
                "extra" => "<table align='center'>
                                    <tr style='background-color:#d3e3f3;'>
                                        <th><h5>Employee Name</h5></th>
                                        <th><h5>Logged Hours</h5></th>
                                        <th><h5>Team Name</h5></th>
                                    </tr>
                                    $tableRows
                                </table>"
            ];
        }

        // Return the final data as a JSON object with numeric values
        return json_encode($data, JSON_NUMERIC_CHECK);
    }

    /**
     * @author Mahasri.K
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose calculate top 10 tasks with the highest logged hours
     * */
    public function mostTimeSpendIssues($fromDate, $toDate, $employeeId, $team)
    {
        // SQL query to get the top 10 tasks with the highest logged hours
        $sql = "SELECT 
                u.firstname AS user_name, 
                i.subject, 
                ROUND(SUM(te.hours), 2) AS total_hours,
                i.id as redmine_id
            FROM 
                users u
            INNER JOIN 
                time_entries te ON u.id = te.user_id
            INNER JOIN 
                issues i ON te.issue_id = i.id
            LEFT JOIN 
                custom_values ON custom_values.customized_id = u.id
            INNER JOIN custom_fields ON custom_fields.id = custom_values.custom_field_id 
                AND custom_fields.name = 'Team'
            WHERE 
                te.spent_on BETWEEN :fromDate: AND :toDate: 
                AND u.status = 1"; // Filter by date range

        // Append the query if the employee ID is not in the list
        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND u.parent_id = :employeeId:";
        }
        if ($team != null) {
            // Apply the team filter
            $sql .= " AND custom_values.value = :team:";
        }

        // Group the result by user and task (subject), order by total logged hours in descending order
        $sql .= " GROUP BY 
                u.id, i.subject , i.id
            ORDER BY 
                total_hours DESC
            LIMIT 10"; // Limit the result to top 10 tasks

        // Execute the query with the date range and employee ID
        $execute = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'employeeId' => $employeeId, 'team' => $team]);
        $result = $execute->getResultArray();
        $data = [];
        // Group the data by user_name and aggregate the extra field (task details)
        foreach ($result as $value) {
            // Check if the user already exists in the data array
            if (!isset($data[$value["user_name"]])) {
                $data[$value["user_name"]] = [
                    "label" => $value["user_name"],
                    "y" => 0,
                    "optional" => $value["subject"],
                    "extra" => "",
                ];
            }

            // Aggregate total hours and build the extra table content for each user
            $data[$value["user_name"]]["y"] += (float) $value["total_hours"];
            $data[$value["user_name"]]["extra"] .= "
        <tr>
            <td style='padding:10px; color:black; text-align:center;'>" . $value["redmine_id"] . "</td>
            <td style='padding:10px; color:black; text-align:center;'>" . $value["subject"] . "</td>
            <td style='padding:10px; color:black; text-align:center;'>" . (float) $value["total_hours"] . "</td>
        </tr>
        ";
        }

        // Convert the grouped data into an array for chart display
        $chartData = [];
        foreach ($data as $user => $value) {
            $chartData[] = [
                "label" => $value["label"],
                "y" => $value["y"],
                "optional" => $value["optional"],
                "extra" => "<table align='center'>
                            <tr style='background-color:#d3e3f3;'>
                                <th><h5>Redmine Id</h5></th>
                                <th><h5>Consuming Issues</h5></th>
                                <th><h5>Consumed Hours</h5></th>
                            </tr>" .
                    $value["extra"] .
                    "</table>"
            ];
        }

        // Return the data as a JSON object
        return json_encode($chartData, flags: JSON_NUMERIC_CHECK);
    }

    /**
     * @author Mahasri.K
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose calculate the top 10 employees by the number of projects they have worked on
     * */

    public function totalProjectCount($fromDate, $toDate, $employeeId, $team)
    {
        // SQL query to get the top 10 employees by project count
        $sql = "SELECT
                        u.firstname AS Name, 
                        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS ProjectNames,
                        COUNT(DISTINCT t.project_id) AS ProjectCount 
                    FROM 
                        users u
                    INNER JOIN 
                        time_entries t ON t.user_id = u.id
                    INNER JOIN 
                        projects p ON t.project_id = p.id   
                    LEFT JOIN 
                        custom_values ON custom_values.customized_id = u.id
                    INNER JOIN custom_fields ON custom_fields.id = custom_values.custom_field_id 
                        AND custom_fields.name = 'Team'
                    WHERE 
                        t.created_on BETWEEN :fromDate: AND :toDate: AND u.status=1"; // Filter by date range
        // Append the query if the employee ID is not in the list
        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND u.parent_id = :employeeId:";
        }
        if ($team != null) {
            // Apply the team filter
            $sql .= " AND custom_values.value = :team:";
        }
        // Group the result by user and order by project count in descending order  
        $sql .= " GROUP BY 
                                u.id
                            ORDER BY 
                                ProjectCount DESC
                            LIMIT 10;"; // Limit the result to top 10 employees by project count

        // Execute the query with the specified date range and employee ID
        $execute = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'employeeId' => $employeeId, 'team' => $team]);
        $result = $execute->getResultArray();

        $data = [];
        // Split the project names by commas and build the table rows for each project
        foreach ($result as $value) {
            $array = explode(",", $value["ProjectNames"]);
            $empName = [];
            foreach ($array as $val) {
                $empName[] = "<tr>
                                    <td style='padding:10px; color:black; text-align:center;'>$val<td>
                                </tr>";
            }
            $empName = implode('', $empName);
            // Prepare the data for returning as JSON
            $data[] = [
                "label" => $value["Name"], // Employee name as label
                "y" => $value["ProjectCount"], // Project count as value
                "optional" => $value["ProjectNames"], // List of project names
                "extra" => "<table  align='center' width='200px'>
                                    <tr style='background-color:#d3e3f3;'>
                                        <th style='text-align:center;'><h5>Project Name</h5></th>
                                    </tr>
                                    $empName
                                </table>" // Display the project names in a table format
            ];
        }
        // Return the data as a JSON object
        return json_encode($data, JSON_NUMERIC_CHECK);
    }

    /**
     * @author Mahasri.K, Bharath.J.R, Kavitha.L
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose calculate unlogged employees (employees who haven't logged time in the given date range)
     * */

    public function unloggedEmployees($fromDate, $toDate, $employeeId, $team)
    {
        // SQL query to get the list of unlogged employees (employees with no time entries in the date range)
        $sql = "SELECT 
                        u.firstname AS Name, 
                        cv.value AS Team
                    FROM 
                        users u
                    LEFT JOIN 
                        custom_values cv 
                    ON cv.customized_id = u.id 
                    AND cv.custom_field_id = (
                    SELECT id 
                    FROM custom_fields 
                    WHERE name = 'Team'
                    )
                    LEFT JOIN 
                        time_entries te 
                    ON te.user_id = u.id
                    LEFT JOIN 
                        projects p 
                    ON te.project_id = p.id
                    WHERE 
                        u.id NOT IN (
                            SELECT te.user_id 
                            FROM time_entries te
                            WHERE te.spent_on BETWEEN :fromDate: AND :toDate:
                        )
                        AND cv.value IS NOT NULL
                        AND u.status=1 -- Filter active users -->
                        AND u.created_on < :fromDate: ";
        // Append the query if the employee ID is not in the list
        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND u.parent_id = :employeeId:";
        }
        if ($team != null) {
            // Apply the team filter
            $sql .= " AND cv.value = :team:";
        }
        $sql .= " AND u.id not in :id: 
                                GROUP BY 
                                    u.firstname, cv.value"; // Group by user name and team

        // Execute the query with the specified date range and employee ID
        $execute = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'employeeId' => $employeeId, 'id' => $this->id, 'team' => $team]);
        $result = $execute->getResultArray();
        // Build the data array with name and team for each unlogged employee
        $data = [];
        foreach ($result as $value) {
            // Prepare the data for returning as JSON
            $data[] = [
                "lable" => $value["Name"], // Employee name as label
                "y" => $value["Team"], // Team name
            ];
        }
        // Return the unlogged employees as a list
        return $data;
    }

    /**
     * @author Mahasri.K, Kavitha.L
     * @created 12-NOV-2024
     * @updated-Date 15-NOV-2024
     * @purpose calculate efficient Redmine users based on time entries dates
     * */

    public function efficientRedmineUsers($fromDate, $toDate, $employeeId, $team)
    {
        // SQL query to calculate users efficiency time entries dates
        $sql = "SELECT
                        efficient_logged_hours,
                        COUNT(*) AS user_count,
                        GROUP_CONCAT(DISTINCT CONCAT(Name, ' |', team_name) ORDER BY Name ASC) AS user_names
                    FROM (
                        SELECT
                            u.firstname AS Name,
                            custom_values.value AS team_name,
                            COUNT(DISTINCT DATE(te.created_on)) AS created_date_count,
                            COUNT(DISTINCT DATE(te.spent_on)) AS spent_date_count,
                            COUNT(DISTINCT CASE WHEN DATE(te.spent_on) = DATE(te.created_on) THEN te.spent_on END) AS correct_entries_count,
                            CASE 
                                WHEN COUNT(DISTINCT CASE WHEN DATE(te.spent_on) = DATE(te.created_on) THEN te.spent_on END) = COUNT(DISTINCT DATE(te.spent_on)) THEN '100%'
                                WHEN COUNT(DISTINCT CASE WHEN DATE(te.spent_on) = DATE(te.created_on) THEN te.spent_on END) >= ROUND(COUNT(DISTINCT DATE(te.spent_on)) * 0.9) THEN '90%'
                                WHEN COUNT(DISTINCT CASE WHEN DATE(te.spent_on) = DATE(te.created_on) THEN te.spent_on END) >= ROUND(COUNT(DISTINCT DATE(te.spent_on)) * 0.8) THEN '80%'
                                ELSE 'Below 80%'
                            END AS efficient_logged_hours
                        FROM 
                            time_entries te
                        INNER JOIN 
                            users u ON te.user_id = u.id
                        LEFT JOIN custom_values 
                            ON custom_values.customized_id = u.id
                        INNER JOIN custom_fields 
                            ON custom_fields.id = custom_values.custom_field_id 
                            AND custom_fields.name = 'Team'
                        WHERE 
                            te.spent_on BETWEEN :fromDate: AND :toDate: AND u.status=1"; // Filter by date range
        // Append the query if the employee ID is not in the list
        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND u.parent_id = :employeeId:";
        }
        if ($team != null) {
            // Apply the team filter
            $sql .= " AND custom_values.value = :team:";
        }
        // Group the result by user and efficiency level
        $sql .= " GROUP BY 
                                    u.id, u.firstname, custom_values.value
                                HAVING 
                                    efficient_logged_hours != 0
                                    ) AS subquery
                                GROUP BY 
                                    efficient_logged_hours
                                ORDER BY 
                                    efficient_logged_hours DESC;"; // Order by efficient hours in descending order
        // Execute the query with the specified date range and employee ID
        $query = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'employeeId' => $employeeId, 'team' => $team]);
        $result = $query->getResultArray();

        $data = [];
        foreach ($result as $row) {
            // Process each row to display efficiency and user names
            $efficiency = $row["efficient_logged_hours"];
            $userCount = $row["user_count"];
            $userNames = explode(",", $row["user_names"]);
            $tableRows = [];
            foreach ($userNames as $team) {
                $teamName = explode("|", $team);
                $tableRows[] = "<tr>
                                        <td style='padding:10px; color:black; text-align:center;'>" . trim($teamName[0]) . "</td>
                                        <td style='padding:10px; color:black; text-align:center;'>" . trim($teamName[1]) . "</td>
                                    </tr>";
            }
            // Prepare the data for returning as JSON
            $data[] = [
                "label" => $efficiency,  // Efficiency level as label
                "y" => $userCount, // User count based on efficiency level
                "optional" => $efficiency,  // Efficiency level as optional
                "extra" => "<table align='center' width='500px'>
                                    <tr style='background-color:#d3e3f3;'>
                                        <th><h5>Employee Name</h5></th>
                                        <th><h5>Team Name</h5></th>
                                    </tr>
                                    " . implode('', $tableRows) . "
                                </table>" // Display employee names and team names in a table
            ];
        }
        // Return the efficiency data as a JSON object                    
        return json_encode($data, JSON_NUMERIC_CHECK);
    }

    /**
     * @author Mahasri.K
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose retrieve user count by project and team within a date range
     * */

    public function projectWiseUserCount($fromDate, $toDate, $employeeId, $team)
    {
        // SQL query to select project name, team, task count, and user count by project and team
        $sql = "SELECT 
            p.name AS project_name,
            COUNT(i.id) AS task_count,
            COUNT(DISTINCT u.id) AS user_count,
            cv.value AS team,
            GROUP_CONCAT(DISTINCT CONCAT(u.firstname, ': ', u.lastname)) as user_names
            FROM 
                projects p
            INNER JOIN 
                issues i ON p.id = i.project_id
            INNER JOIN 
                users u ON i.assigned_to_id = u.id
            INNER JOIN 
                custom_values cv ON cv.customized_id = u.id 
                AND cv.custom_field_id = :id:
            INNER JOIN custom_fields ON custom_fields.id = cv.custom_field_id 
                AND custom_fields.name = 'Team'
            WHERE 
                i.created_on BETWEEN :fromDate: AND :toDate: 
                AND u.status = 1";

        // Append the condition if the employee ID is not in the list
        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND u.parent_id = :employeeId:";
        }
        if ($team != null) {
            // Apply the team filter
            $sql .= " AND cv.value = :team:";
        }
        // Add grouping and ordering
        $sql .= "
                GROUP BY 
                    p.name, cv.value
                ORDER BY 
                    p.name, cv.value;"; // Sort by project name and team name

        // Execute the SQL query with the specified date range and employeeId
        $result = $this->db->query($sql, ['fromDate' => $fromDate, 'toDate' => $toDate, 'employeeId' => $employeeId, 'id' => $this->reportConfig->team_id, 'team' => $team]);
        $result = $result->getResultArray(); // Get the result as an associative array

        // Initialize an array to store the pivoted data (project-wise and team-wise user counts)
        $pivotedData = [];
        $uniqueTeams = []; // Array to store unique teams

        // Process each row from the result and organize the data into a pivoted format
        foreach ($result as $row) {
            $project = $row['project_name']; // Project name from the current row
            $taskCount = $row['task_count']; // Task count (number of tasks) for the current project and team
            $userCount = $row['user_count']; // User count (number of distinct users) for the current project and team
            $team = $row['team']; // Team name from the current row
            $userNames = $row['user_names'];


            // Initialize the project data structure if it doesn’t already exist
            if (!isset($pivotedData[$project])) {
                $pivotedData[$project] = [
                    'Total User Count' => 0, // Initialize total user count for this project
                    'Total Task Count' => 0, // Initialize total task count for this project
                ];
            }

            // Store the user count for the specific team under the project
            $pivotedData[$project][$team] = [
                'userCount' => $userCount,
                'userNames' => $userNames
            ];
            // Calculate the total task count for the project (across all teams)
            $pivotedData[$project]['Total Task Count'] += $taskCount;
            // Add the team to the unique teams array (no duplicate teams)
            $uniqueTeams[$team] = $team;
        }

        // After populating the pivoted data, calculate the total user count per project by summing team-specific user counts
        foreach ($pivotedData as $project => &$data) {
            // Sum the user counts across all unique teams for this project
            $data['Total User Count'] = array_sum(array_column($data, 'userCount'));
        }

        // Return the pivoted data (project-wise and team-wise user counts) and unique team names
        return [
            'pivotedData' => $pivotedData, // Pivoted data with user counts per project and team
            'uniqueTeams' => array_values($uniqueTeams) // List of unique teams
        ];
    }

    /**
     * @author Mahasri.K
     * @created 26-OCT-2024
     * @updated-Date 15-NOV-2024
     * @purpose retrieve project wise tracker and hours within a date range
     * */

    public function projectTracker($fromDate, $toDate, $employeeId, $team)
    {
        $sql = "SELECT 
            ROUND(SUM(te.hours), 2) AS total_hours, 
            p.name AS project_name, 
            t.name AS tracker_name, 
            COUNT(DISTINCT u.id) AS total_users,
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    u.firstname, ': (', u.lastname, ', ', user_hours.total_user_hours,' hrs)')
            ) AS user_names
            FROM 
                projects p
            INNER JOIN 
                issues i ON p.id = i.project_id
            INNER JOIN 
                trackers t ON i.tracker_id = t.id
            INNER JOIN 
                time_entries te ON i.id = te.issue_id
            INNER JOIN 
                users u ON i.assigned_to_id = u.id
            LEFT JOIN 
                custom_values ON custom_values.customized_id = u.id
            INNER JOIN custom_fields ON custom_fields.id = custom_values.custom_field_id 
                AND custom_fields.name = 'Team'
            INNER JOIN (
                SELECT 
                    u.id AS user_id,
                    i.project_id AS project_id,
                    i.tracker_id AS tracker_id,
                    round(SUM(te.hours),2) AS total_user_hours
                FROM 
                    time_entries te
                INNER JOIN 
                    issues i ON te.issue_id = i.id
                INNER JOIN 
                    users u ON i.assigned_to_id = u.id
                WHERE 
                    te.spent_on BETWEEN :fromDate: AND :toDate: 
                    AND u.status = 1
                GROUP BY 
                    u.id, i.project_id, i.tracker_id
            ) AS user_hours 
                ON user_hours.project_id = i.project_id 
                AND user_hours.tracker_id = t.id 
                AND user_hours.user_id = u.id
            WHERE 
                te.spent_on BETWEEN :fromDate: AND :toDate:
                AND u.status = 1";

        // Add the employee filter if necessary
        if (!in_array($employeeId, $this->id)) {
            $sql .= " AND u.parent_id = :employeeId:";
        }
        if ($team != null) {
            // Apply the team filter
            $sql .= " AND custom_values.value = :team:";
        }

        $sql .= "
                GROUP BY 
                    p.name, t.name 
                ORDER BY 
                    p.name ASC, t.name ASC;
            ";
        $result = $this->db->query($sql, [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'employeeId' => $employeeId,
            'team' => $team
        ]);
        $result = $result->getResultArray();

        // Initialize pivoted data structure
        $pivotedData = [];
        $uniqueTrackers = []; // To store unique tracker names

        // Process the result and populate the pivoted data
        foreach ($result as $row) {
            $totalHours = ROUND($row['total_hours'], 2);
            $project = $row['project_name'];
            $tracker = $row['tracker_name'];
            $users = $row['total_users'];
            $userNames = $row['user_names'];

            // Initialize the project entry if it doesn't exist
            if (!isset($pivotedData[$project])) {
                $pivotedData[$project] = [
                    'Total Hours' => 0, // Initialize total hours for the project
                    'Total User Count' => 0, // Initialize user count for the project
                ];
            }

            // Store the user count and total hours for each tracker under the project
            $pivotedData[$project][$tracker] = [
                'userCount' => $users,
                'totalHours' => $totalHours,
                'userNames' => $userNames // Store the user names with their total hours for hovering
            ];

            // Add tracker to unique trackers list
            $uniqueTrackers[$tracker] = $tracker;

            // Increment the total hours for the project
            $pivotedData[$project]['Total Hours'] = ROUND($pivotedData[$project]['Total Hours'] + $totalHours, 2);

            $pivotedData[$project]['Total User Count'] += $users;
        }

        // Return pivoted data with unique trackers
        return [
            'pivotedData' => $pivotedData,  // Pivoted data with project, tracker-wise user count and hours
            'uniqueTrackers' => array_values($uniqueTrackers), // List of unique trackers
        ];
    }
}
?>