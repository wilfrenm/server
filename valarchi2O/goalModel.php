<?php

namespace App\Models\Goals;

use App\Models\BaseModel;

class GoalsModel extends BaseModel
{
    /*
     * @category   GOAL LIST 
     * @author     KARVINBRITTO M
     * @created    27 OCT 2024  
     */

    /**
     * @author KARVINBRITTO M
     * @return Array
     * Purpose: This function is used to Get Goals list with filters in the Database 
     */
    public function getFilterGoalsList($filter, $employeeId): array
    {
        $sqlwhere = "goals.is_deleted = :is_deleted:  AND goals.r_assigned_user = :assigned_user:  ";

        if (!empty($filter['start_date']) && !empty($filter['end_date'])) {
            $sqlwhere .= " AND ((goals.start_date BETWEEN :start_date: AND :end_date:)  OR (goals.end_date  BETWEEN :start_date: AND :end_date:)) ";
        }
        if (!empty($filter['status'])) {
            $sqlwhere .= " AND goals.r_goal_status_id =:goal_status:  ";
        }
        if (!empty($filter['stakeholder'])) {
            $sqlwhere .= " AND goals.r_stakeholder_id = :stakeholder:  ";
        }
        if (!empty($filter['category'])) {
            $sqlwhere .= " AND goals.r_category_id = :category:  ";
        }
        if (!empty($filter['objective'])) {
            $sqlwhere .= " AND goals.r_objective_id = :objective: ";
        }
        if (!empty($filter['searchQuery'])) {
            $sqlwhere .= " AND (goals.goal_name LIKE :search:)  ";
        }
        // SQL query to get goals list
        $sql = "SELECT
          goals.goal_name, 
          goals.start_date, 
          goals.end_date AS target_date,
          statuss.status_name AS status,
          goals.completed_percentage AS completed_percentage,
          stakeholder.stakeholder_name AS stakeholder,
          COUNT(task.goal_task_id) AS total_tasks,
          SUM(CASE WHEN module_statusss.r_status_id = 15 AND module_statusss.r_module_id = 23 THEN 1 ELSE 0 END) AS completed_tasks,
          objective.objective_name AS objective,
          category.category_name AS category,
          sf.feedback AS employee_feedback,
          er.rating AS employee_rating,
          mf.feedback AS manager_feedback,
          mr.rating AS manager_rating,
          goals.actual_end_date,
          goals.updated_date,
          goals.effective_spent_hours AS spent_hours,
          goals.goal_id AS id,
          stakeholder.stakeholder_id,
          objective.objective_id,
          category.category_id,
          goals.r_assigned_user AS assigned_user_id,
          er.rating_no AS employee_rating_no,
          goals.r_goal_status_id AS status_id 
      FROM 
          scrum_goals AS goals
	        LEFT JOIN scrum_module_status AS module_status
          ON goals.r_goal_status_id = module_status.module_status_id
	        LEFT JOIN scrum_goal_tasks AS task
          ON task.r_goal_id = goals.goal_id
            AND task.is_deleted = 'N'
	        LEFT JOIN scrum_module_status AS module_statusss
          ON module_statusss.module_status_id = task.r_goal_task_status_id
	        LEFT JOIN scrum_status AS statuss
          ON module_status.r_status_id = statuss.status_id        
	        LEFT JOIN scrum_goal_stakeholder AS stakeholder
          ON goals.r_stakeholder_id = stakeholder.stakeholder_id
          LEFT JOIN scrum_goal_objective AS objective
          ON goals.r_objective_id = objective.objective_id
          LEFT JOIN scrum_goal_category AS category  
          ON goals.r_category_id = category.category_id
          LEFT JOIN scrum_goal_feedback sf
          ON goals.employee_feedback_id = sf.feedback_id
	        LEFT JOIN scrum_goal_feedback mf
          ON goals.manager_feedback_id = mf.feedback_id
	        LEFT JOIN scrum_goal_rating er 
          ON goals.r_self_rating = er.rating_no
	        LEFT JOIN scrum_goal_rating mr 
          ON goals.r_manager_rating = mr.rating_no
                
              WHERE $sqlwhere 
              GROUP BY
              goals.goal_id
              ORDER BY 
              goals.updated_date DESC";

        // Execute the query with parameter binding
        $query = $this->query($sql, [
            'stakeholder' => $filter['stakeholder'],
            'objective' => $filter['objective'],
            'category' => $filter['category'],
            'start_date' => $filter['start_date'],
            'end_date' => $filter['end_date'],
            'goal_status' => $filter['status'],
            'search' => '%' . $filter['searchQuery'] . '%',
            'is_deleted' => 'N',
            'assigned_user' => $employeeId
        ]);

        //return the output array
        if ($query) {
            return $query->getResultArray();
        }
    }


    /**
     * @author KARVINBRITTO M
     * @return Boolean
     * Purpose: This function is used to Delete the Goals in the Database 
     */
    public function deleteGoal($id)
    {
        // SQL query to delete goal    
        $sql = "UPDATE       
          scrum_goals
        SET 
          is_deleted = :is_deleted:
        WHERE
          goal_id=:goal_id:";

        // Execute the query with parameter binding
        $result = $this->db->query($sql, [
            'is_deleted' => 'Y',
            'goal_id' => $id
        ]);

        // Return the boolean result 
        if ($result) {
            return true;
        }
        return false;
    }

    /**
     * @author KARVINBRITTO M
     * @return Array
     * Purpose: This function is used to get Goals status in the Database 
     */
    public function getGoalStatus()
    {
        // SQL query to get goals status    
        $sql = "SELECT 
          module_status.module_status_id,
          statuss.status_name as status
        FROM 
          scrum_status AS statuss
          INNER JOIN scrum_module_status AS module_status
          ON statuss.status_id = module_status.r_status_id 
        WHERE 
           statuss.is_deleted = :is_deleted: and module_status.r_module_id = :r_module_id: ";

        // Execute the query with parameter binding
        $result = $this->db->query($sql, [
            'is_deleted' => 'N',
            'r_module_id' => 22
        ]);

        // Return the result as an associative array
        return $result->getResultArray();
    }

    /**
     * @author KARVINBRITTO M
     * @return Array
     * Purpose: This function is used to Get Stakeholder list in the Database 
     */
    public function getStakeholder($stakeholderName = null)
    {
        // SQL query to get goals Stakeholder    
        $sql = "SELECT 
          stakeholder_id,
          stakeholder_name
        FROM 
          scrum_goal_stakeholder
        WHERE 
          is_deleted = :is_deleted: ";

        if (!empty($stakeholderName)) {
            $sql .= " AND stakeholder_name = :stakeholder_name:  ";
        }
        // Execute the query with parameter binding
        $result = $this->db->query($sql, [
            'is_deleted' => 'N',
            'stakeholder_name' => $stakeholderName
        ]);

        // Return the result as an associative array
        return $result->getResultArray();
    }

    /**
     * @author KARVINBRITTO M
     * @return Array
     * Purpose: This function is used to Get Objective list in the Database 
     */
    public function getObjective($stakeholderId = null)
    {
        // SQL query to get goals Objective    
        $sql = "SELECT 
          objective_id,
          objective_name
        FROM 
          scrum_goal_objective
        WHERE 
          is_deleted = :is_deleted: ";

        if (!empty($stakeholderId)) {
            $sql .= " AND r_stakeholder_id = :stakeholder_id:  ";
        }

        // Execute the query with parameter binding
        $result = $this->db->query($sql, [
            'stakeholder_id' => $stakeholderId,
            'is_deleted' => 'N'
        ]);


        // Return the result as an associative array
        return $result->getResultArray();
    }

    /**
     * @author KARVINBRITTO M
     * @return Array
     * Purpose: This function is used to Get Category list in the Database 
     */
    public function getCategory($data = null)
    {
        // SQL query to get goals Category    
        $sql = "SELECT 
           category_id,
           category_name
         FROM 
           scrum_goal_category
         WHERE 
           is_deleted = :is_deleted: ";
        $params = ['is_deleted' => 'N'];
        // $params=['is_deleted' => 'N'];
        if (!empty($data['categoryName'])) {
            $sql .= " AND category_name = :category:  ";
            $params['category'] = $data['categoryName'];
        }
        if (!empty($data['objectiveId'])) {
            $sql .= " AND r_objective_id = :objective_id:  ";
            $params['objective_id'] = $data['objectiveId'];
        }
        // Execute the query with parameter binding
        $result = $this->db->query($sql, $params);

        // Return the result as an associative array
        return $result->getResultArray();
    }

    /**
     * @author KARVINBRITTO M
     * @return Array
     * Purpose: This function is used to Get Ratings list in the Database 
     */
    public function getRatings()
    {
        // SQL query to get goals Ratings    
        $sql = "SELECT 
           rating_no,
           rating
         FROM 
           scrum_goal_rating
         WHERE 
           is_deleted = :is_deleted: ";

        // Execute the query with parameter binding
        $result = $this->db->query($sql, [
            'is_deleted' => 'N'
        ]);
        // Return the result as an associative array
        return $result->getResultArray();
    }



    /*
     * @category   FILTER AND LIST OF GOAL TASK 
     * @author     SUDHARSANAN
     * @created    27 OCT 2024
     */

    //Method for filter and search 
    public function filterGoalTaskItem($filter)
    {
        // Initialize an empty query filter
        $queryfilter = "";

        // Check if status, fromDate, and toDate are provided
        if (!empty($filter["filterStatus"]) && !empty($filter["fromDate"]) && !empty($filter["toDate"])) {
            // If all three are provided, filter by status and date range
            $queryfilter = "r_goal_task_status_id=:r_goal_task_status_id: and ((start_date>=:start_date: and start_date<=:end_date:) or (end_date>=:start_date: and end_date<=:end_date:)) and ";
        }

        // If only fromDate and toDate are provided
        else if (!empty($filter["fromDate"]) && !empty($filter["toDate"])) {
            // Filter by date range only
            $queryfilter = "((start_date>=:start_date: and start_date<=:end_date:) or (end_date>=:start_date: and end_date<=:end_date:)) and ";
        }

        // If only filterStatus is provided
        else if (!empty($filter["filterStatus"])) {
            // Filter by task status only
            $queryfilter .= "r_goal_task_status_id=:r_goal_task_status_id: and ";
        }

        // If no filters are provided, keep the query filter empty
        else {
            $queryfilter = "";
        }

        // Check if a search query is provided for goal task name
        if (!empty($filter['searchQuery'])) {
            // Add a filter for goal task name using LIKE operator
            $queryfilter .= "(scrum_goal_tasks.goal_task_name LIKE :search:) AND ";
        }

        // Construct the SQL query with dynamic filters
        $sql = "SELECT scrum_goal_tasks.goal_task_id as goal_task_id,
                      scrum_goal_tasks.r_goal_id as r_goal_id,
                      scrum_goal_tasks.goal_task_name as goal_task_name,
                      scrum_goal_tasks.start_date as start_date,
                      scrum_goal_tasks.end_date as end_date,
                      scrum_goal_tasks.r_goal_task_status_id as status_id,
                      scrum_status.status_name as status_name,
                      scrum_goal_tasks.completed_percentage as completed_percentage
                FROM 
                  scrum_goal_tasks
                INNER JOIN 
                  scrum_module_status ON scrum_goal_tasks.r_goal_task_status_id=scrum_module_status.module_status_id
                INNER JOIN 
                  scrum_status ON scrum_status.status_id = scrum_module_status.r_status_id
                WHERE $queryfilter
                scrum_goal_tasks.r_goal_id =:goalId: 
                AND scrum_goal_tasks.is_deleted= :is_deleted:
                ORDER BY scrum_goal_tasks.updated_date desc;";

        // Execute the query with the parameters passed in the $filter array
        $query = $this->query($sql, [
            "goalId" => $filter["goalID"],
            "r_goal_task_status_id" => $filter["filterStatus"],
            "start_date" => $filter["fromDate"],
            "end_date" => $filter["toDate"],
            "search" => '%' . $filter['searchQuery'] . '%', // Search query with a wildcard for partial matching
            'is_deleted' => 'N'
        ]);

        // Check if the query was successful and return the results
        if ($query) {
            return $query->getResultArray(); // Return filtered task results as an array
        }
        // If query fails, return false (could be extended to handle errors)
        return false;
    }


    // /*
    //   * @category   INSERT GOAL TASK 
    //   * @author     SUDHARSANAN
    //   * @created    27 OCT 2024
    //   */

    // // Method to insert a new goal task into the scrum_goal_tasks table
    // public function insertGoalTask($data)
    // {
    //   // Define the SQL query to insert a new goal task
    //   $sql = "INSERT INTO scrum_goal_tasks (
    //                   r_goal_id,                    
    //                   goal_task_name,               
    //                   r_goal_task_status_id,        
    //                   start_date,                   
    //                   end_date,                     
    //                   completed_percentage, 
    //                   created_date,               
    //                   is_deleted                    
    //               )
    //               VALUES (
    //                   :r_goal_id:,                
    //                   :goal_task_name:,           
    //                   :r_goal_task_status_id:,    
    //                   :start_date:,                 
    //                   :end_date:,                 
    //                   :completed_percentage:,       
    //                   now(),                        
    //                   :is_deleted:                  
    //               )";

    //   // Execute the query with the provided data
    //   $query = $this->db->query($sql, [
    //     'r_goal_id' => $data['r_goal_id'],
    //     'goal_task_name' => $data['goal_task_name'],
    //     'r_goal_task_status_id' => $data['status_name'],
    //     'start_date' => $data['start_date'],
    //     'end_date' => $data['end_date'],
    //     'completed_percentage' => $data['completed_percentage'],
    //     'is_deleted' => 'N'
    //   ]);

    //   //  Check if the query was successful
    //   if ($query) {
    //     //If insertion was successful, return the inserted record's ID
    //     return $this->db->insertID();
    //   }
    // }

    /*
     * @category   STATUS FOR GOAL TASK 
     * @author     SUDHARSANAN
     * @created    27 OCT 2024
     */

    // Method to get goal task status
    public function getGoalTaskStatus($taskModuleId)
    {
        // Define the SQL query to get goal task status
        $sql = "SELECT 
          module_status.module_status_id,
          statuss.status_name
        FROM 
          scrum_status AS statuss
          INNER JOIN scrum_module_status AS module_status
          ON statuss.status_id = module_status.r_status_id 
        WHERE 
          module_status.r_module_id = :r_module_id:";

        // Execute the query with the provided data
        $result = $this->db->query($sql, [
            'r_module_id' => $taskModuleId
        ]);

        if ($result) {
            return $result->getResultArray();
        }

        if ($result) {
            return $result->getResultArray();
        }
    }


    // /*
    //   * @category   UPDATE GOAL TASK 
    //   * @author     SUDHARSANAN
    //   * @created    27 OCT 2024
    //   */

    // // Method to update goal task status
    // public function updateGoalTask($data)
    // {

    //   // Define the SQL query to update goal task 
    //   $sql = "UPDATE scrum_goal_tasks 
    //               SET goal_task_name= :goal_task_name:,
    //                   start_date=:start_date:,
    //                   end_date=:end_date:,
    //                   r_goal_task_status_id=:r_goal_task_status_id:,
    //                   completed_percentage=:completed_percentage:,
    //                   updated_date=now()
    //                WHERE goal_task_id=:taskId:";

    //       // Execute the query with the provided data
    //       $result = $this->db->query($sql, [  
    //         'goal_task_name' => $data["goal_task_name"],
    //         'start_date' => $data["start_date"],
    //         'end_date' => $data["end_date"],
    //         'r_goal_task_status_id' => $data["status_name"],
    //         'completed_percentage' => $data["completed_percentage"],
    //         'taskId'=>$data["goal_task_id"]
    //       ]);

    //       if ($result) {  
    //         return $this->db->affectedRows();
    //       }

    //       return false;
    //   } 

    /*
     * @category   DELETE GOAL TASK 
     * @author     SUDHARSANAN
     * @created    27 OCT 2024
     */


    // Method to soft delete goal task 
    public function deleteGoalTask($goalTaskId)
    {

        // Define the SQL query to Soft delete goal task 
        $query = "UPDATE scrum_goal_tasks SET is_deleted=:is_deleted:
         WHERE goal_task_id=:goal_task_id:
            ";

        // Execute the query with the provided data
        $result = $this->db->query($query, [
            "is_deleted" => "Y",
            "goal_task_id" => $goalTaskId
        ]);

        if ($result) {
            return true;
        }
    }

    /*
     * @category   goal details (like goal name and status)  
     * @author     SUDHARSANAN
     * @created    27 OCT 2024   
     */

    // Method get the goal details (like goal name and status) from the database based on the provided goalId
    public function getGoalDetails($goalId)
    {

        // Define the SQL query to get goal details
        $sql = "SELECT DISTINCT goal_name  as goal_name,
            r_goal_status_id as status_id,
            start_date,
            end_Date,
            r_assigned_user
          FROM scrum_goals 
          WHERE goal_id=:goal_id: ;";

        // Execute the query with the provided data
        $result = $this->query($sql, [
            'goal_id' => $goalId
        ]);

        if ($result->getResultArray() > 0) {
            return $result->getResultArray();
        }
        return '';
    }








    public function getGoalReview($id)
    {
        $sql = "
          SELECT 
              su.first_name AS Employee,
              sg.goal_id,
              sg.r_assigned_user,
              sg.goal_name AS goal_name,
              sg.end_date AS Target_Date, 
              sg.actual_end_date AS Actual_EndDate,
              sg.completed_percentage AS Completed_percentage, 
              s.status_name AS STATUS_Name, 
              gr.rating AS Self_Rating,
              sf.feedback AS Self_Feedback
          FROM 
              scrum_goals AS sg 
          INNER JOIN
              scrum_user AS su ON su.external_employee_id = sg.r_assigned_user
          INNER JOIN 
              scrum_module_status AS ms ON sg.r_goal_status_id = ms.module_status_id
          INNER JOIN 
              scrum_status AS s ON s.status_id = ms.r_status_id 
          LEFT JOIN 
              scrum_goal_rating AS gr ON gr.rating_id = sg.r_self_rating 
          LEFT JOIN
              scrum_goal_feedback AS sf ON sg.employee_feedback_id = sf.feedback_id
              WHERE sg.is_deleted = :is_deleted: AND sg.r_assigned_user=:user:
           ORDER BY 
              sg.updated_date DESC";

        $result = $this->db->query($sql, ['is_deleted' => 'N', 'user' => $id]);
        return $result->getResultArray();
    }
    public function getGoalname($id)
    {
        $sql = "SELECT 
            first_name 
            from 
            scrum_user
            WHERE external_employee_id = :id: ";
        $result = $this->db->query($sql, ['id' => $id]);
        return $result->getResultArray();
    }
    public function getGoalRating()
    {
        $sql = "SELECT 
          rating_id,
          rating
        FROM 
          scrum_goal_rating
        WHERE 
          is_deleted = :is_deleted: ";
        $result = $this->db->query($sql, [
            'is_deleted' => 'N'
        ]);
        return $result->getResultArray();
    }
    public function updateGoalBoard($goalid, $updaterating, $updatefeedback, $id)
    {

        $insertsql = "INSERT INTO scrum_goal_feedback (feedback, r_goal_id,r_user_id)
                      VALUES (:feedback:, :goal:, :user:)";
        $insertresult = $this->db->query($insertsql, [
            'feedback' => $updatefeedback,
            'goal' => $goalid,
            'user' => $id
        ]);
        $updatequery = "UPDATE scrum_goals sg
                    INNER JOIN scrum_goal_feedback sgf ON sgf.r_goal_id = sg.goal_id 
                    SET sg.manager_feedback_id = sgf.feedback_id
                    WHERE sgf.r_user_id = :user_id:";
        $this->db->query($updatequery, [
            'user_id' => $id  // Bind the r_user_id value
        ]);
        $sql = "UPDATE scrum_goals
          SET r_manager_rating = :rating:
          WHERE goal_id = :goal: ";
        $result = $this->db->query($sql, [
            'rating' => $updaterating,      // Correctly bind the rating parameter
            'goal' => $goalid               // Bind the goal_id parameter
        ]);

        $updatedquery = "UPDATE scrum_goals 
                    SET r_goal_status_id = :stat:
                    WHERE goal_id = :goal:";
        $this->db->query($updatedquery, [
            'goal' => $goalid,
            'stat' => 40
        ]);
    }

    public function goalboardfilter($filtervalue, $id)
    {
        $sqlwhere = "sg.is_deleted = :is_deleted: AND sg.r_assigned_user = :user:";
        if (!empty($filtervalue['start_date']) || !empty($filtervalue['end_date'])) {
            $sqlwhere .= " AND sg.start_date >= :start_date: AND sg.end_date <= :end_date:";
        }
        if (!empty($filtervalue['status'])) {
            $sqlwhere .= " AND sg.r_goal_status_id = :goal_status:";


        }
        if (!empty($filtervalue['manager'])) {
            $sqlwhere .= " AND sg.r_assigned_user= :manager:";

        }
        if (!empty($filtervalue['date'] == "thisMonth")) {
            $sqlwhere .= " AND MONTH(sg.end_date) = MONTH(CURDATE())";

        }
        if (!empty($filtervalue['date'] == "lastMonth")) {
            $sqlwhere .= " AND MONTH(sg.end_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)";

        }
        $sql = "
  SELECT 
      su.first_name AS Employee,
      sg.goal_id,
      sg.r_assigned_user,
      sg.goal_name AS goal_name,
      sg.end_date AS Target_Date, 
      rating.rating,
      feedback.feedback AS managerfeedback,
      sg.actual_end_date AS Actual_EndDate,
      sg.completed_percentage AS Completed_percentage, 
      s.status_name AS STATUS_Name, 
      gr.rating AS Self_Rating,
      sf.feedback AS Self_Feedback
  FROM 
      scrum_goals AS sg 
  INNER JOIN
      scrum_user AS su ON su.external_employee_id = sg.r_assigned_user
  INNER JOIN 
      scrum_module_status AS ms ON sg.r_goal_status_id = ms.module_status_id
  LEFT JOIN
      scrum_goal_rating AS rating ON rating.rating_no =sg.r_manager_rating

  INNER JOIN 
      scrum_status AS s ON s.status_id = ms.r_status_id 
  LEFT JOIN 
      scrum_goal_rating AS gr ON gr.rating_id = sg.r_self_rating 
  LEFT JOIN
      scrum_goal_feedback AS sf ON sg.employee_feedback_id = sf.feedback_id
   LEFT JOIN
    scrum_goal_feedback AS feedback ON sg.manager_feedback_id= feedback.feedback_id
  WHERE
      $sqlwhere
      ORDER BY 
              sg.updated_date DESC";


        $query = $this->query($sql, [
            'user' => $id,
            'start_date' => $filtervalue['start_date'],
            'end_date' => $filtervalue['end_date'],
            'goal_status' => $filtervalue['status'],
            'is_deleted' => 'N',
            'manager' => $filtervalue['manager'],
        ]);
        if ($query) {
            return $query->getResultArray();
        }
    }

    /*
     * @category   Get Goal Count based on the Month Year user
     * @author     SUDHARSANAN
     * @created    18 NOV 2024
     */


    public function goalsCountMonthlyRating($data)
    {
        $sql = "
      SELECT 
          COUNT(*) AS assigned_goals_count, 
          SUM(CASE WHEN r_goal_status_id = :goal_status: THEN 1 ELSE 0 END) AS review_completed_count
      FROM scrum_goals
      WHERE MONTH(end_date) = :month:
        AND YEAR(end_date) = :year:
        AND r_assigned_user = :r_assigned_user:
        AND is_deleted=:is_deleted:  
      GROUP BY r_assigned_user
  ";

        $query = $this->query($sql, [
            'r_assigned_user' => $data["r_user_id"],
            'month' => $data['month'],
            'year' => $data['year'],
            'goal_status' => 40,
            'is_deleted' => 'N',
        ]);
        if ($query) {
            return $query->getResultArray();
        }
    }

    /*
     * @category   check the availablity of the monthly rating
     * @author     SUDHARSANAN
     * @created    18 NOV 2024
     */

    public function checkAvailablity($data)
    {
        $sql = "SELECT * 
              FROM scrum_people_monthly_rating 
              WHERE r_user_id = :user_id:
              AND people_rating_month = :month:
              AND people_rating_year = :year:
              AND is_deleted = 'N'";

        $query = $this->db->query($sql, [
            'user_id' => $data['r_user_id'],
            'month' => $data['month'],
            'year' => $data['year']
        ]);

        return $query->getRowArray(); // Return the existing row or null
    }


    /*
     * @category   GET PEOPLE RATING FOR DROPDOWN
     * @author     SUDHARSANAN
     * @created    13 NOV 2024
     */

    public function peopleRating()
    {
        $sql = "SELECT people_rating ,people_rating_no 
            FROM scrum_goal_people_rating ";

        $result = $this->db->query($sql);
        return $result->getResultArray();
    }

    /*
     * @category   MONTHLY RATING INSERT FOR SELF
     * @author     SUDHARSANAN
     * @created    13 NOV 2024
     */


    // // Insert a self-rating
    // public function insertSelfRating($data)
    // {
    //     $sql = "INSERT INTO scrum_people_monthly_rating 
    //             (r_user_id, people_rating_month, people_rating_year, self_monthly_rating_id, r_self_monthly_feedback, is_deleted) 
    //             VALUES 
    //             (:user_id:, :month:, :year:, :rating:, :feedback:, 'N')";

    //     $query = $this->db->query($sql, [
    //         'user_id' => $data['user'],
    //         'month' => $data['month'],
    //         'year' => $data['year'],
    //         'rating' => $data['rating'],
    //         'feedback' => $data['feedback']
    //     ]);

    //     return $query ? $this->db->insertID() : false;
    // }

    /*
     * @category   MONTHLY RATING UPDATE FOR REVIEWER
     * @author     SUDHARSANAN
     * @created    13 NOV 2024
     */

    // public function updateManagerRating($data)
    // {
    //     $sql = "UPDATE scrum_people_monthly_rating 
    //             SET reviewer_monthly_rating_id = :rating:, r_manager_monthly_feedback = :feedback: 
    //             WHERE r_user_id = :user_id: 
    //             AND people_rating_month = :month: 
    //             AND people_rating_year = :year: 
    //             AND is_deleted = 'N'";

    //     $query = $this->db->query($sql, [
    //         'user_id' => $data['user'],
    //         'month' => $data['month'],
    //         'year' => $data['year'],
    //         'rating' => $data['rating'],
    //         'feedback' => $data['feedback']
    //     ]);

    //     return $query ? true : false;
    // }


    /*
     * @category   TASK DETAILS BASED ON GOAL NAME (FOR PDF)
     * @author     SUDHARSANAN
     * @created    13 NOV 2024
     */

    public function taskDetails($goal_id)
    {
        $sql = "SELECT  scrum_goals.goal_name AS goal_name,
              GROUP_CONCAT(scrum_goal_tasks.goal_task_name ORDER BY scrum_goal_tasks.start_date ASC SEPARATOR ', ') AS task_names,
              GROUP_CONCAT(scrum_goal_tasks.start_date ORDER BY scrum_goal_tasks.start_date ASC SEPARATOR ', ') AS start_dates,
              GROUP_CONCAT(scrum_goal_tasks.end_date ORDER BY scrum_goal_tasks.start_date ASC SEPARATOR ', ') AS end_dates,
              GROUP_CONCAT(scrum_goal_tasks.completed_percentage ORDER BY scrum_goal_tasks.start_date ASC SEPARATOR ', ') AS completed_percentages,
              GROUP_CONCAT(scrum_status.status_name ORDER BY scrum_goal_tasks.start_date ASC SEPARATOR ', ') AS status_names
              FROM 
                  scrum_goal_tasks
              inner join 
                  scrum_module_status on scrum_goal_tasks.r_goal_task_status_id = scrum_module_status.module_status_id
              inner join 
                  scrum_status ON scrum_status.status_id = scrum_module_status.r_status_id 
              inner join 
                  scrum_goals on scrum_goal_tasks.r_goal_id=scrum_goals.goal_id 
              WHERE 
                  scrum_goal_tasks.r_goal_id=:goal_id:
                  and scrum_goal_tasks.is_deleted=:is_deleted:
              GROUP BY 
                  scrum_goals.goal_id;
              ";

        $result = $this->db->query($sql, [
            'goal_id' => $goal_id,
            'is_deleted' => "N"
        ]);
        return $result->getResultArray();
    }

    /*
     * @category   GOALS STATUS FOR ASSIGNED GOALS AND COMPLETED GOALS
     * @author     SUDHARSANAN
     * @created    13 NOV 2024
     */

    public function goalsStatus($id, $filters)
    {

        $queryString = "";
        if ($id != 0) {
            $queryString = "AND r_goal_status_id= :status:";
        }
        // Apply date filters if provided
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $queryString .= " AND start_date >= :start_date: AND end_date <= :end_date:";
            $params['start_date'] = $filters['start_date'];
            $params['end_date'] = $filters['end_date'];
        }

        $sql = "SELECT COUNT(goal_id) as count 
      FROM scrum_goals 
      WHERE r_assigned_user=:employeeId:
      $queryString
      and is_deleted=:is_deleted:";
        $result = $this->db->query($sql, [
            'employeeId' => $filters["manager"],
            "status" => $id,
            "start_date" => $filters["start_date"],
            "end_date" => $filters["end_date"],
            'is_deleted' => "N"
        ]);
        return $result->getResultArray();
    }

    /*
     * @category   Get MONTHLY RATING(FOR PDF)
     * @author     SUDHARSANAN
     * @created    13 NOV 2024
     */

    public function getMonthlyManagerRating($filters, $id)
    {
        $sql = "
          SELECT 
              manager.people_rating AS manager,
              self.people_rating AS self,
              COUNT(g.goal_id) AS total_goals,
              YEAR(g.end_date) AS year,
              MONTH(g.end_date) AS month
          FROM 
              scrum_goals AS g
          LEFT JOIN 
              scrum_people_monthly_rating
              ON g.r_created_user = scrum_people_monthly_rating.r_user_id
              AND YEAR(g.end_date) = scrum_people_monthly_rating.people_rating_year
              AND MONTH(g.end_date) = scrum_people_monthly_rating.people_rating_month
          LEFT JOIN 
              scrum_goal_people_rating AS manager
              ON manager.people_rating_no = scrum_people_monthly_rating.reviewer_monthly_rating_id
          LEFT JOIN 
              scrum_goal_people_rating AS self
              ON self.people_rating_no = scrum_people_monthly_rating.self_monthly_rating_id
          WHERE 
              g.is_deleted = :is_deleted:
              AND g.r_goal_status_id = :status:
              AND g.start_date >= :start_date:
              AND g.end_date <= :end_date:
              AND g.r_assigned_user = :employeeId:
          GROUP BY
              scrum_people_monthly_rating.people_rating_year,
              scrum_people_monthly_rating.people_rating_month,
              manager.people_rating,
              self.people_rating
      ";

        // Execute the query with the filters
        $result = $this->db->query($sql, [
            'employeeId' => $filters["manager"],
            'status' => $id,
            'start_date' => $filters["start_date"],
            'end_date' => $filters["end_date"],
            'is_deleted' => 'N'
        ]);

        // Return the result as an associative array
        return $result->getResultArray();
    }
}
