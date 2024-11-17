<?php
function groupByCommits($jenkinResponse)
{
    // $branch = ['dev', 'test', 'uat', 'prelive', 'live'];
    $jenkinResponseArray = json_decode($jenkinResponse, true);
    $branches = (array_values(array_unique(array_column($jenkinResponseArray, 'branch_name'))));
    $branch = ['dev' => $branches[0], 'test' => $branches[1], 'uat' => $branches[2], 'prelive' => $branches[3], 'live' => $branches[4]];
    print_r($jenkinResponseArray);
    // die;
    $newGroupdata = [
        'upto_dev' => [],
        'upto_test' => [],
        'upto_uat' => [],
        'upto_prelive' => [],
        'upto_live' => [],
        'test_skipped' => [],
        'uat_skipped' => [],
    ];

    foreach ($jenkinResponseArray as $key => $value) {
        // Ensure 'tracked_status' exists
        if (isset($value['tracked_status'])) {
            $tracked_status = $value['tracked_status'];
            // Check if all necessary branch keys exist in 'tracked_status'
            if (isset($tracked_status[$branch['dev']])) {
                if (isset($tracked_status[$branch['test']]) && isset($tracked_status[$branch['uat']]) && isset($tracked_status[$branch['prelive']]) && isset($tracked_status[$branch['live']])) {
                    // Check if tracked status for all stages exist
                    // $newGroupdata['upto_live'][] = $value;
                } elseif (isset($tracked_status[$branch['test']]) && isset($tracked_status[$branch['uat']]) && isset($tracked_status[$branch['prelive']])) {
                    // If only up to prelive
                    $newGroupdata['upto_prelive'][] = $value;
                } elseif (isset($tracked_status[$branch['test']]) && isset($tracked_status[$branch['uat']])) {
                    // If only up to UAT
                    $newGroupdata['upto_uat'][] = $value;
                } elseif (isset($tracked_status[$branch['test']])) {
                    // If only up to Test
                    $newGroupdata['upto_test'][] = $value;
                } elseif (isset($tracked_status[$branch['uat']]) && !isset($tracked_status[$branch['test']])) {   // test skipped
                    $newGroupdata['test_skipped'][] = $value;
                } elseif (isset($tracked_status[$branch['prelive']]) && !isset($tracked_status[$branch['uat']])) {    // UAT skipped
                    $newGroupdata['uat_skipped'][] = $value;
                } elseif (isset($tracked_status[$branch['live']]) && !isset($tracked_status[$branch['prelive']])) {   // Prelive skipped
                    $newGroupdata['prelive_skipped'][] = $value;
                } else {
                    $newGroupdata['upto_dev'][] = $value;
                }
            }
        }
    }

    foreach ($newGroupdata as $key => $value) {
        $stat[$key] = count($value);
        foreach ($value as $k => $v) {
            $fileName[] = $value[$k]['file_name'];
        }
    }

    // print_r($newGroupdata);
    // die;


    // // Populate the mapping
    // foreach ($jenkinResponseArray as $value) {
    //     $commitToBranchMap[$value['commit_id']] = $value['branch_name'];
    // }


    // Initialize grouped array

    // filenames and no of files on grouped commit ids
    // foreach ($grouped_data as $key => $value) {
    //     $stat[$key] = count($value);
    //     foreach ($value as $k => $v) {
    //         $fileName[] = $value[$k]['file_name'];
    //     }
    // }


    // echo "<pre>";
    // print_r($stat);
    // echo count($grouped_data['upto_dev']);
    // print_r($grouped_data);
    // print_r($jenkinResponseArray);
    // die;

    // $data = [$grouped_data, $stat, array_unique($fileName)];

    // Call and Store a value in cache
    // $cache = Services::cache();
    // $cache->save('groupedJenkinResponse', $newGroupdata, 900);  // 900 seconds (15 minutes)

    $group_data = $newGroupdata;
    $data = [$group_data, $stat];
    return $data;
}
?>