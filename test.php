<?php
function getProjects()
{
    $redmine = service('redmine');
    $client = $redmine->getClient(session('redmine_api_key'));
    $users = $client->getApi('issue')->all();
    // $users = $client->getApi('project')->show('5');
    // (['limit' => 50, 'offset' => 50]);
    print_r($users);
    // $projects = [
    //     'cms', 'cbt', 'b2b'
    // ];
    return json_encode($users);
}

function commitStatus()
{
    // Jenkin response curl 
    // $groupedData;
    $jenkinResponsePath = APPPATH . 'views/releaseManagement/jenkinResponse.json';
    if (file_exists($jenkinResponsePath)) {
        $jenkinResponse = file_get_contents($jenkinResponsePath);
        $jenkinResponseArray = json_decode($jenkinResponse, true);

        // Create an associative array mapping commit IDs to branch names
        $commitToBranchMap = [];

        // Populate the mapping
        foreach ($jenkinResponseArray as $value) {
            $commitToBranchMap[$value['commit_id']] = $value['branch_name'];
        }

        foreach ($jenkinResponseArray as $key => $value) {
            if (preg_match_all('/cherry picked from commit ([a-f0-9]{40})/', $value['commit_message'], $matches)) {

                // Initialize tracked_status if it doesn't exist
                if (!isset($jenkinResponseArray[$key]['tracked_status']) && !isset($jenkinResponseArray[$key]['track_id'])) {
                    $jenkinResponseArray[$key]['tracked_status'] = [];
                    $jenkinResponseArray[$key]['track_id'] = 0;
                }

                foreach ($matches[1] as $commitId) {
                    // Use the mapping to find the current branch name
                    $currentBranchName = isset($commitToBranchMap[$commitId]) ? $commitToBranchMap[$commitId] : 'cherry-picked commit id not found in these branches';
                    $jenkinResponseArray[$key]['tracked_status'][$currentBranchName] = $commitId;
                }
                $env = [
                    1 => ['amal_dev'],
                    2 => ['amal_dev', 'amal_test'],
                    3 => ['amal_dev', 'amal_test', 'amal_uat'],
                    4 => ['amal_dev', 'amal_test', 'amal_uat', 'branches/AMAL_PRELIVE'],
                    5 => ['amal_dev', 'amal_uat', 'amal_live'],
                    6 => ['amal_dev', 'amal_test', 'branches/AMAL_PRELIVE', 'amal_live'],
                    7 => ['amal_dev', 'amal_test', 'amal_uat', 'branches/AMAL_PRELIVE', 'amal_live']
                ];

                if (isset($jenkinResponseArray[$key]['tracked_status'])) {
                    // Get branches in tracked_status
                    $trackedBranches = array_keys($jenkinResponseArray[$key]['tracked_status']);
                    foreach ($env as $id => $branches) {
                        // Check if all branches in this environment are present in tracked_status
                        if (empty(array_diff($branches, $trackedBranches))) {
                            // Update if all required branches for this environment level are matched
                            $jenkinResponseArray[$key]['track_id'] = $id;
                        }
                    }
                }
            } else {
                // echo "cherry pick not found in ";
            }
        }

        // Initialize grouped array
        $grouped_data = [
            'upto_dev' => [],
            'upto_test' => [],
            'upto_uat' => [],
            'upto_prelive' => [],
            'upto_live' => []
        ];

        // Loop through each entry in the response
        foreach ($jenkinResponseArray as $key => $deployment) {

            // Group based on the highest environment reached
            if (isset($deployment['tracked_status']['branches/AMAL_PRELIVE'])) {
                if (!isset($deployment['tracked_status']['amal_uat']) && !isset($deployment['tracked_status']['amal_test'])) {
                    $grouped_data['uat_prelive_skipped'][] = $deployment; // Both uat and prelive skipped
                } elseif (!isset($deployment['tracked_status']['amal_uat'])) {
                    $grouped_data['uat_skipped'][] = $deployment; // Uat skipped, prelive to live
                } elseif (!isset($deployment['tracked_status']['amal_test'])) {
                    $grouped_data['test_skipped'][] = $deployment; // test skipped, prelive to live
                } else {
                    $grouped_data['upto_live'][] = $deployment;
                }
            } elseif (isset($deployment['tracked_status']['amal_uat'])) {
                // Prelive reached, check if UAT was skipped
                if (!isset($deployment['tracked_status']['amal_test'])) {
                    $grouped_data['test_skipped'][] = $deployment; // test skipped, uat to prelive
                } else {
                    $grouped_data['upto_prelive'][] = $deployment;
                }
                // $grouped_data['upto_prelive'][] = $deployment;
            } elseif (isset($deployment['tracked_status']['amal_test'])) {
                $grouped_data['upto_uat'][] = $deployment;
            } elseif (isset($deployment['tracked_status']['amal_dev'])) {
                $grouped_data['upto_test'][] = $deployment;
            } elseif (($deployment['branch_name'] == 'amal_dev')) {
                $grouped_data['upto_dev'][] = $deployment;
            }
        }

        // echo "<pre>";
        // Output the grouped array
        foreach ($grouped_data as $key => $value) {
            // print_r($key);
            $stat[$key] = count($value);
            foreach ($value as $k => $v) {
                $fileName[] = $value[$k]['file_name'];
            }
        }
        // print_r(array_unique($fileName));
        // print_r($stat);

        // echo count($grouped_data['upto_dev']);

        // print_r($grouped_data);
        // print_r($jenkinResponseArray);
        // die;
        $cache = Services::cache();

        // Store a value in cache
        $cache->save('groupedJenkinResponse', $grouped_data, 900); // 300 seconds (5 minutes)
        // $grouped_data['files'] = $stat;
        // print_r($grouped_data);
        // die;
    } else {
        echo json_encode(['error' => 'JSON not found']);
    }

    return $this->template_view('releaseManagement/commitStatus', [$grouped_data, $stat, array_unique($fileName)], 'Commit Status', ['Home' => ASSERT_PATH . 'dashboard/dashboardView', 'File Tracking' => ASSERT_PATH . 'commitstatus']);
}

?>