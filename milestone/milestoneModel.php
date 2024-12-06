<?php


namespace App\Models\ProjectPlan;

use CodeIgniter\Model;

use App\Models\BaseModel;

class MilestoneModel extends BaseModel
{
    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method getMilestoneList
     * 
     *      This function is used to give the project plan's entire data from milestone to task with teams to 
     *  give milectone template and the forms.
        return array
    */
    public function getMilestoneList($p_id): array
    {

        $sql = "SELECT milestone.*,project_plan.project_plan_name,project_plan.project_plan_id,scrum_customer.*,teams.*,scrum_status.*,COUNT(scrum_task_status.name) AS task_count,scrum_task_status.name
                FROM milestone
                INNER JOIN project_plan ON project_plan.project_plan_id=milestone.r_project_plan_id
                INNER JOIN scrum_customer ON scrum_customer.customer_id=project_plan.r_customer_id
                INNER JOIN teams ON teams.team_id=milestone.r_team_id 
                INNER JOIN scrum_status ON scrum_status.status_id=milestone.r_status_id
                INNER JOIN scrum_task ON scrum_task.r_milestone_id=milestone.milestone_id
                INNer JOIN scrum_task_status ON scrum_task_status.id=scrum_task.task_status
                WHERE milestone.is_deleted='N' AND milestone.r_project_plan_id=:r_project_plan_id:
                GROUP BY scrum_task_status.name";
        $query = $this->query(
            $sql,
            [
                'r_project_plan_id' => $p_id
            ]
        );
        if ($query->getNumRows() > 0)
            return $query->getResultArray();

        return [];
    }

    /**
     * @author Sainandha
     * @since 01/11/2024
     * @method getMilestoneData
     * @modified Shali S S 16/11/2024
     * @modified Vignesh Manikandan R 18/11/2024
     *  This function is used to show up the milestone list in js format.  --Completion progress and milestone priority
        return string
    */
    public function getMilestoneData($pId, $filter = null)
    {
        $sql = "SELECT 
                    milestone.*, 
                    project_plan.project_plan_name, 
                    project_plan.project_plan_id, 
                    scrum_customer.*, 
                    teams.*, 
                    scrum_status.*, 
                    SUM(scrum_task_status.name = 'Completed' && scrum_task.is_deleted='N') AS completed_status,
                    COUNT(scrum_task_status.name = 'New') AS new_status,
                    SUM(scrum_task.is_deleted = 'N') AS total_task,
                    SUM(scrum_task.completed_percentage) AS total_percentage,
                    SUM(scrum_task.priority = 'M') AS Medium,
                    SUM(scrum_task.priority = 'H') AS High,
                    SUM(scrum_task.priority = 'L') AS Low

                FROM 
                    milestone
                INNER JOIN 
                    project_plan ON project_plan.project_plan_id = milestone.r_project_plan_id
                INNER JOIN 
                    scrum_customer ON scrum_customer.customer_id = milestone.r_customer_id
                INNER JOIN 
                    teams ON teams.team_id = milestone.r_team_id 
                INNER JOIN 
                    scrum_status ON scrum_status.status_id = milestone.r_status_id
                LEFT JOIN 
                    scrum_task ON scrum_task.r_milestone_id = milestone.milestone_id
                LEFT JOIN 
                    scrum_task_status ON scrum_task_status.id = scrum_task.task_status
                WHERE milestone.is_deleted = 'N' AND milestone.r_project_plan_id=?
                ";

        $params = [$pId]; // Initial value for 'is_deleted' and project plan ID

        // Apply customerDeliveryDate filter if provided
        if (isset($filter['custDeliveryDate'])) {
            $sql .= " AND milestone.customer_delivery_date = ?";
            $params[] = $filter['custDeliveryDate'];
        }

        // Apply owner filter if provided
        if (!empty($filter['ownerFilter'])) {
            $data = is_array($filter['ownerFilter']) ? $filter['ownerFilter'] : explode(',', $filter['ownerFilter']);
            $data = implode(',', array_map(fn($item) => $this->db->escape($item), $data));
            $sql .= " AND scrum_customer.customer_id IN ($data)";
        }

        // Apply status filter if provided
        if (!empty($filter['statusFilter'])) {
            $data = is_array($filter['statusFilter']) ? $filter['statusFilter'] : explode(',', $filter['statusFilter']);
            $data = implode(',', array_map(fn($item) => $this->db->escape($item), $data));
            $sql .= " AND scrum_status.status_id IN ($data)";
        }

        // Apply search query if provided
        if (!empty($filter['searchQuery'])) {
            $sql .= " AND (milestone.milestone_title LIKE ?)";
            $params[] = $filter['searchQuery'] . '%';
        }

        $sql .= " GROUP BY milestone.milestone_title";

        // Add LIMIT and OFFSET
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $filter['limit'] ?? 2;
        $params[] = $filter['offset'] ?? 0;

        // Execute the query
        $query = $this->db->query($sql, $params);

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }

        return [];
    }

    /**
     * @author Sainandha
     * @since 01/11/2024
     * @method getGanttMilestone
     *
     *  This function is used to show up the milestone gantt view.
        return string
    */
    public function getGanttMilestone($p_id): array
    {
        $sql = "SELECT 
                    milestone.*, 
                    project_plan.project_plan_name, 
                    project_plan.project_plan_id, 
                    scrum_customer.customer_name, 
                    teams.team_name, 
                    scrum_status.status_name
                FROM milestone
                INNER JOIN project_plan ON project_plan.project_plan_id = milestone.r_project_plan_id
                INNER JOIN scrum_customer ON scrum_customer.customer_id = milestone.r_customer_id
                INNER JOIN teams ON teams.team_id = milestone.r_team_id 
                INNER JOIN scrum_status ON scrum_status.status_id = milestone.r_status_id
                WHERE milestone.is_deleted = 'N' 
                AND milestone.r_project_plan_id = :r_project_plan_id:
                ORDER BY milestone.milestone_id ASC"
        ;

        $query = $this->query($sql, ['r_project_plan_id' => $p_id]);

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method getTask
     * 
     *      This function is used to teams to give milestoneController.
        return array
    */
    public function getTask($p_id): array
    {
        $sql = "SELECT 
                    scrum_task.*, 
                    scrum_task_status.name AS task_status_name,
                    milestone.milestone_id, 
                    milestone.milestone_title, 
                    scrum_user.first_name
                FROM scrum_task
                INNER JOIN milestone ON milestone.milestone_id = scrum_task.r_milestone_id
                INNER JOIN scrum_task_status ON scrum_task_status.id = scrum_task.task_status
                INNER JOIN scrum_user ON scrum_user.external_employee_id=scrum_task.assignee_id
                WHERE milestone.r_project_plan_id = :r_project_plan_id:
                AND scrum_task.is_deleted = 'N'
                ORDER BY scrum_task.start_date ASC";

        $query = $this->query($sql, ['r_project_plan_id' => $p_id]);

        if ($query->getNumRows() > 0)
            return $query->getResultArray();
        return [];
    }

    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method selectTeams
     * 
     *      This function is used to teams to give getMilestoneList().
        return array
    */
    public function selectTeams(): array
    {
        $sql = "SELECT * FROM teams";
        $query = $this->query($sql);
        if ($query->getNumRows() > 0)
            return $query->getResultArray();
        return [];
    }

    /**
     * @author Vignesh Manikandan R
     * @since 02/11/2024
     * @modifiedBy Sai Nandha (05/11/2024)
     * @method getTaskList
     * 
     *      This function is used to give the task list initially Now is using on gantt chart (Handeld by Nandha).
        return array
    */
    public function getTaskList(): array
    {
        $sql = "SELECT 
                    * 
                FROM 
                    scrum_task as st
                INNER JOIN 
                    milestone as m
                ON 
                    m.milestone_id = st.r_milestone_id
                LEFT JOIN
                    teams 
                ON
                    teams.team_id = st.r_team_id
                LEFT JOIN
                    scrum_status as ss
                ON
                    ss.status_id = st.task_status
                LEFT JOIN 
                    scrum_customer  as sc
                ON
                    sc.customer_id = st.r_customer_id
                LEFT JOIN
                    scrum_user  as su
                ON 
                    su.external_employee_id = st.assignee_id
                WHERE 
                    st.is_deleted='N' 
                ORDER BY 
                    m.end_date 
                DESC
                ";
        $query = $this->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method getTaskStatus
     * 
     *      This function is used to task status to give milestoneController.
        return array
    */
    public function getTaskStatus(): array
    {
        $sql = "SELECT 
                    status_id,
                    status_name
                FROM 
                    scrum_status";
        $query = $this->query($sql);
        if ($query)
            return $query->getResultArray();
        return [];
    }

    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method getusers
     * 
     *      This function is used to user to give milestoneController to view form.
        return array
    */
    public function getusers()
    {
        $sql = "SELECT *
                FROM 
                    scrum_user";
        $query = $this->query($sql);
        if ($query)
            return $query->getResultArray();
        return [];
    }

    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method getTasks
     * 
     *      This function is used to task to give milestoneController to view form.
        return array
    */
    public function getTasks()
    {
        $sql = "SELECT * 
            FROM scrum_task";
        $query = $this->query($sql);
        if ($query)
            return $query->getResultArray();
        return [];
    }

    public function getTaskDuration()
    {
        $sql = "SELECT * 
                FROM scrum_sprint_duration";
        $query = $this->query($sql);
        if ($query) {
            return $query->getResultArray();
        }
        return [];
    }
    public function insertData($data)
    {
        $sql = "INSERT INTO scrum_task (
                    task_title,
                    task_description,
                    assignee_id,
                    priority,
                    task_status,
                    completed_percentage,
                    start_date,
                    end_date,
                    actual_end_date,
                    estimated_hours,
                    r_user_id_created,
                    r_user_id_updated,
                    created_date,
                    updated_date,
                    r_dependent_task_id,
                    task_dependency_description,
                    r_milestone_id
                    )
                VALUES (:taskName:,
                    :taskDescription:,
                    :assignee_id:,
                    :priority:,
                    :taskStatus:,
                    :completedPercentage:,
                    :startDate:,
                    :endDate:,
                    :actualEnddate:,
                    :estimatedHours:,
                    :r_user_id_created:,
                    :r_user_id_updated:,
                    :created_date:,
                    :updated_date:,
                    :taskDependency:,
                    :taskdependencyDescription:,
                    :id:
                    )";

        $query = $this->db->query($sql, [
            'taskName' => $data['taskName'],
            'taskDescription' => $data['taskDescription'],
            'assignee_id' => $data['assignee_id'],
            'priority' => $data['priority'],
            'taskStatus' => $data['taskStatus'],
            'completedPercentage' => $data['completedPercentage'],
            'startDate' => $data['startdate'],
            'endDate' => $data['enddate'],
            'actualEnddate' => $data['actualEnddate'],
            'estimatedHours' => $data['estimatedHours'],
            'r_user_id_created' => $data['r_user_id_created'],
            'r_user_id_updated' => $data['r_user_id_updated'],
            'created_date' => $data['created_date'],
            'updated_date' => $data['updated_date'],
            'taskDependency' => $data['taskDependency'],
            'taskdependencyDescription' => $data['taskdependencyDescription'],
            'id' => $data['taskid']
        ]);
        if ($query) {
            return $this->db->insertID();
        }
        return false;
    }
    public function getTaskById($taskId): array
    {
        $sql = "SELECT st.*,sts.name AS status_name,su.first_name 
         FROM scrum_task AS st 
         INNER JOIN scrum_user AS su ON st.assignee_id=su.external_employee_id 
         INNER JOIN scrum_task_status AS sts ON st.task_status=sts.id 
         WHERE st.is_deleted='N' AND st.task_id=:id:";

        $query = $this->query($sql, ['id' => $taskId]);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }
    public function updateData($data)
    {
        $sql = "UPDATE 
                    scrum_task 
                SET 
                    task_title= :taskName:,
                    task_description=:taskDescription:,
                    assignee_id=:assignee_id:,
                    priority=:priority:,
                    task_status=:taskStatus:,
                    completed_percentage=:completedPercentage:,
                    start_date= :startDate:,
                    end_date= :endDate:,
                    actual_end_date=:actualEnddate:,
                    estimated_hours= :estimatedHours:,
                    updated_date=:updated_date:,
                    r_user_id_updated=:updated_user_id:,
                    r_dependent_task_id= :taskDependency:,
                    task_dependency_description=:taskdependencyDescription:
            
                WHERE 
                    task_id = :taskid:";

        $query = $this->db->query($sql, [
            'taskid' => $data['taskid'],
            'taskName' => $data['taskName'],
            'taskDescription' => $data['taskDescription'],
            'assignee_id' => $data['assignee_id'],
            'priority' => $data['priority'],
            'taskStatus' => $data['taskStatus'],
            'completedPercentage' => $data['completedPercentage'],
            'startDate' => $data['startdate'],
            'endDate' => $data['enddate'],
            'actualEnddate' => $data['actualEnddate'],
            'estimatedHours' => $data['estimatedHours'],
            'updated_date' => $data['updated_date'],
            'updated_user_id' => $data['r_user_id_updated'],
            'taskDependency' => $data['taskDependency'],
            'taskdependencyDescription' => $data['taskdependencyDescription']

        ]);
        if ($query) {
            return true;
        }
        return false;
    }

    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @method selectProjectPlanId
     * 
     *     This function is used to get the projectplan id where the add milestone and 
     * edit milestone form didnt pick the project plan id .
        return array
    */
    public function selectProjectPlanId($data)
    {
        $sql = "SELECT project_plan_id 
            FROM project_plan
            WHERE project_plan_name= :project_name:";
        $query = $this->query($sql, ['project_name' => $data]);
        if ($query) {
            return $query->getRow()->project_plan_id;
        }
        return;
    }
    public function selectTeamId($data)
    {
        $sql = "SELECT team_id 
            FROM teams
            WHERE team_name= :team_name:";
        $query = $this->query($sql, ['team_name' => $data]);
        if ($query) {
            return $query->getRow()->team_id;
        }
        return;
    }
    public function selectcustomerId($data)
    {
        $sql = "SELECT customer_id 
            FROM scrum_customer
            WHERE customer_name= :customer_name:";
        $query = $this->query($sql, ['customer_name' => $data]);
        if ($query) {
            return $query->getRow()->customer_id;
        }
        return;
    }

    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @method insertMilestone
     * 
     *     This function is used to insert the mileston's deatils .
        return array
    */
    public function insertMilestone($data)
    {
        $sql = "INSERT INTO milestone(
                milestone_title,
                r_project_plan_id,
                r_customer_id,
                r_team_id,
                r_status_id,
                start_date,
                end_date,
                customer_delivery_date,
                createddate,
                is_deleted)
              VALUES (
                :milestone_title:,
                :r_project_plan_id:,
                :r_customer_id:,
                :r_team_id:,
                :r_status_id:,
                :start_date:,
                :end_date:,
                :customer_delivery_date:,
                NOW(),
                :is_deleted:)";
        $query = $this->db->query($sql, [
            'milestone_title' => $data['milestone_title'],
            'r_project_plan_id' => $data['r_project_plan_id'],
            'r_customer_id' => $data['r_customer_id'],
            'r_team_id' => $data['r_team_id'],
            'r_status_id' => 1,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'customer_delivery_date' => $data['customer_delivery_date'],
            'is_deleted' => 'N'
        ]);
        if ($query) {
            return $this->db->insertID();
        }
        return false;
    }
    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @method deleteMilestone
     * 
     *     This function is used delate particular milestone saftly .
        return array
    */
    public function deleteMilestone($data)
    {
        $sql = "UPDATE 
                    milestone
                SET 
                    is_deleted  = 'Y'
                WHERE 
                    milestone_id = :milestone_id:";

        $this->query($sql, [
            'milestone_id' => $data
        ]);
        return true;
    }

    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @method getMilestoneDetails
     * 
     *     This function is used gat the particular milestone's details for editing purpose.
        return array
    */
    public function getMilestoneDetails($data)
    {
        $sql = "SELECT milestone.*,project_plan.project_plan_name, scrum_customer.*,teams.*
                FROM milestone
                INNER JOIN project_plan ON project_plan.project_plan_id=milestone.r_project_plan_id
                INNER JOIN scrum_customer ON scrum_customer.customer_id=project_plan.r_customer_id
                LEFT JOIN teams ON teams.team_id=milestone.r_team_id 
                INNER JOIN scrum_status ON scrum_status.status_id=milestone.r_status_id
                WHERE milestone.is_deleted='N' AND
                milestone_id=:milestone_id:
        ";
        $query = $this->query($sql, [
            'milestone_id' => $data
        ]);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @method updateMilestone
     * 
     *     This function is used update the particular milestone's details .
     *  Here another function also called where the milestone's start date will be increased or decreased means milestone's end date,
     * Task's start date and end date aslo changing.
     return boolean
    */
    public function updateMilestone($data)
    {
        $sql = "UPDATE milestone
            SET
            milestone_title=:milestone_title:,
            r_project_plan_id=:r_project_plan_id:,
            r_customer_id=:r_customer_id:,
            r_team_id=:r_team_id:,
            start_date=:start_date:,
            end_date=:end_date:,
            customer_delivery_date=:customer_delivery_date:,
            updateddate=NOW()
            WHERE
                milestone_id=:milestone_id:
        ";
        $query = $this->db->query($sql, [
            'milestone_id' => $data['milestone_id'],
            'milestone_title' => $data['milestone_title'],
            'r_project_plan_id' => $data['r_project_plan_id'],
            'r_customer_id' => $data['r_customer_id'],
            'r_team_id' => $data['r_team_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'customer_delivery_date' => $data['customer_delivery_date'],
        ]);
        if ($query) {
            $insertedId = $this->db->insertID();
            $this->addOrSubDaysTasks($data);
            return true;
        }
        return false;
    }

    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @method addOrSubDaysTasks
     * 
     *     This function is used update the particular Task's Dates .
     *  Wheater milestone's start date will be increased or decreased means Task's start date and end date aslo changing.
     return boolean
    */
    public function addOrSubDaysTasks($data)
    {
        $sql = "UPDATE 
                    scrum_task
                SET 
                    start_date = DATE_ADD(start_date, INTERVAL :start_interval: DAY),
                    end_date = DATE_ADD(end_date, INTERVAL :end_interval: DAY)
                WHERE 
                    r_milestone_id = :r_milestone_id:";

        $this->db->query($sql, [
            'r_milestone_id' => $data['milestone_id'],
            'start_interval' => $data['task_start_date'],
            'end_interval' => $data['task_end_date']
        ]);
        return true;
    }

    /**
     * @author Vignesh Manikandan R
     * @since 10/11/2024
     * @method getHolidayDetails
     * 
     *     This function is used get the holidays details.
     return array
    */
    public function getHolidayDetails()
    {
        $sql = "SELECT
                    holiday_start_date,
                    holiday_title
                FROM
                    scrum_holidays
                    ";
        $result = $this->query($sql);
        return $result->getResultArray();
    }

    /**
         * @author Vignesh Manikandan R
         * @since 01/11/2024
         * @method customerDetails
         * 
         *  This function is used to give the customer names for filter functionality.
            return array
        */
    public function customerDetails($p_id)
    {
        $sql = "SELECT 
                    DISTINCT(customer_name),customer_id
                FROM 
                    milestone
                INNER JOIN 
                    scrum_customer ON scrum_customer.customer_id=milestone.r_customer_id
                WHERE 
                    milestone.r_customer_id IN (SELECT milestone.r_customer_id
                                            FROM milestone
                                            INNER JOIN project_plan ON project_plan.project_plan_id = milestone.r_project_plan_id
                                            WHERE project_plan.project_plan_id=:project_plan_id:);";
        $query = $this->query($sql, ['project_plan_id' => $p_id]);
        return $query->getResultArray();
    }

    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method getStatus
     * 
     *  This function is used to give the status for filter functionality.
        return array
    */
    public function getStatus()
    {
        $sql = "SELECT 
                    * 
                FROM 
                scrum_status
                ";
        $query = $this->query($sql);
        return $query->getResultArray();
    }
    public function getTotalCount()
    {
        $sql = "SELECT 
                    COUNT(*) as count
                FROM
                    milestone
                WHERE
                    is_deleted ='N'";
        $query = $this->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();

        }
        return [];
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method addMilestoneTask
     * 
     *  This function is used to delete any tasks.
     */
    public function deleteMilestoneTask($id)
    {
        $sql = "UPDATE 
                    scrum_task
                SET 
                    is_deleted  = 'Y'
                WHERE 
                    task_id = :id:";

        $this->query($sql, [
            'id' => $id
        ]);
        return true;
    }
    public function getproductbyId($mId)
    {
        $sql = "SELECT r_product_id
                FROM project_plan AS pp
                INNER JOIN milestone AS m
                WHERE  pp.project_plan_id=m.r_project_plan_id
                AND m.milestone_id=:mId:";
        $query = $this->query($sql, [
            'mId' => $mId
        ]);
        if ($query) {
            return $query->getRow()->r_product_id;
        }
        return [];
    }
    /**
     * @author Shali S S
     * @since 13/11/2024
     * @method milestonetaskList
     * 
     *  This function is used to update the external task Id in task after the redmine sync in project plan.
     */
    public function milestonetaskList($mId)
    {
        $sql = "SELECT st.*,su.first_name,sts.name AS status_name,st.task_status 
                FROM scrum_task AS st
                LEFT JOIN scrum_user AS su ON st.assignee_id=su.external_employee_id 
                INNER JOIN scrum_task_status AS sts ON sts.id=st.task_status
                WHERE st.is_deleted='N' AND st.r_milestone_id=:mId:
                ";
        $query = $this->query($sql, [
            'mId' => $mId
        ]);
        if ($query) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method updateexternaltaskId
     * 
     *  This function is used to update the external task Id in task after the redmine sync in project plan.
     */
    public function updateexternaltaskId($task)
    {

        $sql = "UPDATE scrum_task SET external_reference_task_id=:redmineId: WHERE task_id=:taskId:";
        $query = $this->db->query($sql, [
            'redmineId' => $task['redmineId'],
            'taskId' => $task['taskId']
        ]);
        if ($query) {
            return true;
        }
        return [];
    }
    /**
     * @author Shali S S
     * @since 20/11/2024
     * @method redminetaskUpdate
     * 
     *  This function is used to update the external task Id in task after the redmine sync in project plan.
     */
    public function redminetaskUpdate($redmineId, $completedPercentage, $taskStatus)
    {
        $sql = "UPDATE scrum_task 
                SET
                task_status=:task_status:,
                completed_percentage=:completed_percentage:
                WHERE external_reference_task_id=:external_reference_task_id:";
        $query = $this->db->query($sql, [
            'task_status' => $taskStatus,
            'completed_percentage' => $completedPercentage,
            'external_reference_task_id' => $redmineId
        ]);
        if ($query) {
            return true;
        }
        return false;
    }
    public function getPendingTasks($userId)
    {
        $sql = "SELECT 
                    t.task_id, 
                    t.task_title, 
                    t.task_description, 
                    t.assignee_id, 
                    u.external_username AS assignee_name,  
                    t.priority, 
                    ts.name AS task_status,  
                    t.completed_percentage, 
                    t.start_date, 
                    t.end_date, 
                    t.estimated_hours, 
                    t.created_date, 
                    t.updated_date,
                    t.r_user_story_id,
                    t.r_customer_id,
                    t.r_team_id,
                    t.r_milestone_id,
                    t.r_document_id,
                    pp.project_plan_name,
                    m.milestone_title
                FROM 
                    scrum_task AS t
                JOIN 
                    milestone AS m ON t.r_milestone_id = m.milestone_id
                JOIN 
                    project_plan AS pp ON m.r_project_plan_id = pp.project_plan_id
                JOIN 
                    scrum_task_status AS ts ON t.task_status = ts.id  
                JOIN scrum_user AS u ON t.assignee_id = u.external_employee_id
                WHERE 
                    ts.name = :status:  
                    AND t.end_date >= CURDATE()
                    AND t.is_deleted = 'N'  
                    AND u.external_employee_id = :user_id: 
                ORDER BY 
                    t.end_date ASC";

        $query = $this->query($sql, [
            'user_id' => $userId
        ]);

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }

        return [];
    } /**
      * @author Meikannan M
      * @since 01/11/2024
      * @method sDelay task
      * 
      *  This function is used to display the Delay task list by user asingnee detailas.
      */

    public function getPendingTask($userId)
    {
        $sql = "SELECT 
        t.task_id, 
        t.task_title, 
        t.task_description, 
        t.assignee_id, 
        u.external_username AS assignee_name,  
        t.priority, 
        ts.name AS task_status,  
        t.completed_percentage, 
        t.start_date, 
        t.end_date, 
        pp.project_plan_name,
        m.milestone_title
    FROM 
        scrum_task AS t
    JOIN 
        milestone AS m ON t.r_milestone_id = m.milestone_id
    JOIN 
        project_plan AS pp ON m.r_project_plan_id = pp.project_plan_id
    JOIN 
        scrum_task_status AS ts ON t.task_status = ts.id  
    JOIN 
        scrum_user AS u ON t.assignee_id = u.user_id  
    WHERE 
         t.assignee_id = :user_id:
        AND t.end_date < NOW()                
        AND t.completed_percentage < 100 
    ORDER BY 
        t.end_date ASC";

        $query = $this->db->query($sql, [
            'user_id' => $userId
        ]);

        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }

        return [];
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method consolidatedTaskList
     * 
     *  This function is used to display the consolidated task list for the project plan.
     */

    public function consolidatedTaskList($projectplanId)
    {
        $sql = "SELECT pp.project_plan_name,pp.project_plan_id, m.milestone_id, m.milestone_title, st.*,su.first_name,sts.name AS status_name,st.completed_percentage AS percentage
        FROM project_plan AS pp
        INNER JOIN milestone AS m ON pp.project_plan_id = m.r_project_plan_id
        INNER JOIN scrum_task AS st ON m.milestone_id = st.r_milestone_id
        INNER JOIN scrum_user AS  su ON st.assignee_id=su.external_employee_id
        INNER JOIN scrum_task_status AS sts ON st.task_status=sts.id
        WHERE st.is_deleted='N' AND pp.project_plan_id=:projectplanId:
        ORDER BY st.updated_date DESC";

        $query = $this->query($sql, ['projectplanId' => $projectplanId]);
        if ($query) {
            return $query->getResultArray();
        }
        return [];
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method getemployeeCode
     * 
     *  This function is used to get the employee Code from scrum user table to retrieve assignee details.
     */
    public function getemployeeCode($strassigneeId)
    {
        $sql = "SELECT external_employee_id,last_name,email_id FROM scrum_user WHERE external_employee_id IN ($strassigneeId)";
        $query = $this->query($sql);
        if ($query->getNumRows() > 0) {
            return $query->getResultArray();
        }
        return [];
    }

    /**
     * @author Vignesh Manikandan R
     * @since 17/11/2024
     * @method selectProjectPlanName
     * 
     *      This function is used to give the projectplan name Where it is empty of milestone doesn't show up project plan name.
     * So it's easily defined that wheater formed list or empty list form selection.
        return array
    */
    public function selectProjectPlanName($data)
    {
        $sql = "SELECT project_plan_name 
            FROM project_plan
            WHERE project_plan_id= :project_id:";
        $query = $this->query($sql, ['project_id' => $data]);
        if ($query) {
            return $query->getRowArray();
        }
        return;
    }
    /**
     * @author Vignesh Manikandan R
     * @since 17/11/2024
     * @method customerData
     * 
     *  This function is used to give the particular paroject's customer data for form selection.
        return array
    */
    public function customerData($p_id)
    {
        $sql = "SELECT 
                    *
                FROM 
                    scrum_customer
                INNER JOIN
                    project_plan 
                ON 
                    project_plan.r_customer_id=scrum_customer.customer_id
                WHERE
                    project_plan.project_plan_id=:project_plan_id:";
        $query = $this->query($sql, ['project_plan_id' => $p_id]);
        return $query->getResultArray();
    }
    /**
     * @author Vignesh Manikandan R
     * @since 17/11/2024
     * @method updateMilestoneStatus
     * 
     *  This function is used to update the particular milestone's status on each and every window load.
        return boolean
    */
    public function updateMilestoneStatus($status_name, $milestone_id)
    {
        $sql = "UPDATE 
                    milestone
                SET 
                    r_status_id  = (SELECT status_id FROM scrum_status WHERE status_name=:status_name:)
                WHERE 
                    milestone_id = :milestone_id:";
        $this->query($sql, [
            'status_name' => $status_name,
            'milestone_id' => $milestone_id
        ]);
        return true;
    }
    /**
     * @author Vignesh Manikandan R
     * @since 17/11/2024
     * @method taskStatusUpdate
     * 
     *  This function is used to update the particular task's status on the kanban action.
        return boolean
    */
    public function taskStatusUpdate($data)
    {
        $sql = "UPDATE scrum_task
                JOIN milestone ON scrum_task.r_milestone_id = milestone.milestone_id
                SET scrum_task.task_status=(SELECT id FROM scrum_task_status WHERE name=:status:) ,scrum_task.updated_date=NOW()
                WHERE milestone.milestone_title = :milestone_title:
                AND scrum_task.task_title = :task_title:";

        $this->query($sql, [
            'milestone_title' => $data['m_name'],
            'task_title' => $data['t_name'],
            'status' => $data['status'],
        ]);
        return true;
    }
    /**
     * @author Vignesh Manikandan R
     * @since 24/11/2024
     * @method miletoneEnddateChanging
     * 
     *      This function is used to update the particular milestone's end date for the rendering each and 
     * every time based on the last task end date.
        return boolean
    */
    public function miletoneEnddateChanging($data)
    {
        $sql = "UPDATE milestone
                SET end_date = (
                    SELECT MAX(scrum_task.end_date)
                    FROM scrum_task
                    WHERE scrum_task.r_milestone_id = :milestone_id:
                )
                WHERE milestone_id =:milestone_id: ";
        $this->query($sql, [
            'milestone_id' => $data
        ]);
        return true;
    }
    /**
     * @author Vignesh Manikandan R
     * @since 26/11/2024
     * @method getMilestoneId
     * 
     *      This function is used to get the milestone id by milestone's name.
        return array
    */
    public function getMilestoneId($data)
    {
        $sql = "SELECT milestone_id
                FROM milestone
                WHERE milestone_title=:milestone_name:";
        $query = $this->query($sql, [
            'milestone_name' => $data
        ]);
        return $query->getRowArray();
    }


}


?>