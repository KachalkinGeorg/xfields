<?php

//
// XFields configuration manipulations
//
function xfields_rpc_group_modify($params)
{
    global $userROW, $lang;
    include_once root.'plugins/xfields/xfields.php';
    if (!is_array($xf = xf_configLoad())) {
        $xf = [];
    }
    if (!is_array($userROW) || ($userROW['status'] != 1)) {
        return ['status' => 0, 'errorCode' => 1, 'errorText' => $lang['xfields_msgi_er_security']];
    }
    if (!is_array($params) || !isset($params['action'])) {
        return ['status' => 0, 'errorCode' => 2, 'errorText' => $lang['xfields_msgi_not_active']];
    }
    switch ($params['action']) {
        case 'grpAdd':
            $grpId = $params['id'];
            $grpName = $params['name'];
            // Check for correct name
            if (!preg_match('#^[a-zA-Z0-9_]{2,}$#', $grpId, $null)) {
                return ['status' => 0, 'errorCode' => 3, 'errorText' => $lang['xfields_msgi_er_symvol_grp']];
            }
            // Check for duplicates
            if (isset($xf['grp.news'][$grpId])) {
                return ['status' => 0, 'errorCode' => 4, 'errorText' => $lang['xfields_msgi_dubl_id_grp']];
            }
            // Create group
            $xf['grp.news'][$grpId] = ['title' => $grpName, 'entries' => []];
            xf_configSave($xf);

            // Notify about changes
            return ['status' => 1, 'errorCode' => 0, 'errorText' => $lang['xfields_msgi_add_new_grp'], 'config' => $xf['grp.news']];
        case 'grpEdit':
            $grpId = $params['id'];
            $grpName = $params['name'];
            // Check if group exists
            if (!isset($xf['grp.news'][$grpId])) {
                return ['status' => 0, 'errorCode' => 5, 'errorText' => $lang['xfields_msgi_not_grp']];
            }
            // Modify group
            $xf['grp.news'][$grpId]['title'] = $grpName;
            xf_configSave($xf);

            // Notify about changes
            return ['status' => 1, 'errorCode' => 0, 'errorText' => $lang['xfields_msgi_changed'], 'config' => $xf['grp.news']];
        case 'grpDel':
            $grpId = $params['id'];
            // Check if group exists
            if (!isset($xf['grp.news'][$grpId])) {
                return ['status' => 0, 'errorCode' => 5, 'errorText' => $lang['xfields_msgi_not_grp']];
            }
            unset($xf['grp.news'][$grpId]);
            xf_configSave($xf);

            // Notify about changes
            return ['status' => 1, 'errorCode' => 0, 'errorText' => $lang['xfields_msgi_del_grp'], 'config' => $xf['grp.news']];
        case 'fldAdd':
            $grpId = $params['id'];
            $fldId = $params['field'];
            // Check if group exists
            if (!isset($xf['grp.news'][$grpId])) {
                return ['status' => 0, 'errorCode' => 5, 'errorText' => $lang['xfields_msgi_not_grp']];
            }
            // Check if field already exists
            if (array_search($fldId, $xf['grp.news'][$grpId]['entries']) !== false) {
                return ['status' => 0, 'errorCode' => 6, 'errorText' => $lang['xfields_msgi_is_membe_grp']];
            }
            // Check if field exists
            if (!isset($xf['news'][$fldId])) {
                return ['status' => 0, 'errorCode' => 7, 'errorText' => $lang['xfields_msgi_not_exists']];
            }
            array_push($xf['grp.news'][$grpId]['entries'], $fldId);
            xf_configSave($xf);

            // Notify about changes
            return ['status' => 1, 'errorCode' => 0, 'errorText' => $lang['xfields_msgi_add_grp'], 'config' => $xf['grp.news']];
        case 'fldDel':
        case 'fldUp':
        case 'fldDown':
            $grpId = $params['id'];
            $fldId = $params['field'];
            if (!isset($xf['grp.news'][$grpId])) {
                return ['status' => 0, 'errorCode' => 5, 'errorText' => $lang['xfields_msgi_not_grp']];
            }
            // Check if field already exists
            if (array_search($fldId, $xf['grp.news'][$grpId]['entries']) === false) {
                return ['status' => 0, 'errorCode' => 8, 'errorText' => $lang['xfields_msgi_not_membe_grp']];
            }
            $position = array_search($fldId, $xf['grp.news'][$grpId]['entries']);
            $msg = $lang['xfields_msgi_not_changed'];

            // Decide an action
            if ($params['action'] == 'fldDel') {
                unset($xf['grp.news'][$grpId]['entries'][$position]);
                $msg = $lang['xfields_msgi_deleted'];
            }
            if (($params['action'] == 'fldUp') && ($position > 0)) {
                $tmp = $xf['grp.news'][$grpId]['entries'][$position - 1];
                $xf['grp.news'][$grpId]['entries'][$position - 1] = $xf['grp.news'][$grpId]['entries'][$position];
                $xf['grp.news'][$grpId]['entries'][$position] = $tmp;
                $msg = $lang['xfields_msgi_moved_up'];
            }
            if (($params['action'] == 'fldDown') && (($position + 1) < count($xf['grp.news'][$grpId]['entries']))) {
                $tmp = $xf['grp.news'][$grpId]['entries'][$position + 1];
                $xf['grp.news'][$grpId]['entries'][$position + 1] = $xf['grp.news'][$grpId]['entries'][$position];
                $xf['grp.news'][$grpId]['entries'][$position] = $tmp;
                $msg = $lang['xfields_msgi_moved_down'];
            }
            $xf['grp.news'][$grpId]['entries'] = array_values($xf['grp.news'][$grpId]['entries']);
            xf_configSave($xf);

            // Notify about changes
            return ['status' => 1, 'errorCode' => 0, 'errorText' => $msg, 'config' => $xf['grp.news']];
    }

    return ['status' => 1, 'errorCode' => 0, 'errorText' => 'OK, '.var_export($params, true)];
}

function xfields_rpc_demo($params)
{
    return ['status' => 1, 'errorCode' => 0, 'errorText' => var_export($params, true)];
}

rpcRegisterFunction('plugin.xfields.demo', 'xfields_rpc_demo');
rpcRegisterFunction('plugin.xfields.group.modify', 'xfields_rpc_group_modify');
