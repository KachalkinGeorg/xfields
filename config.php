<?php

// Protect against hack attempts
if (!defined('NGCMS')) {
    die('HAL');
}
// Load lang files
LoadPluginLang('xfields', 'config');
LoadPluginLang('xfields', 'config', '', 'xfconfig', '#');
include_once root.'plugins/xfields/xfields.php';
if (!is_array($xf = xf_configLoad())) {
    $xf = [];
}
//
// Управление необходимыми действиями
$sectionID = $_REQUEST['section'];
if (!in_array($sectionID, ['news', 'grp.news', 'users', 'grp.users', 'tdata'])) {
    $sectionID = 'news';
}

switch ($_REQUEST['action']) {
    case 'add':
        showAddEditForm();
        break;
    case 'doadd':
        doAddEdit();
        break;
    case 'edit':
        showAddEditForm();
        break;
    case 'doedit':
        doAddEdit();
        break;
    case 'update':
        doUpdate();
        break;
    case 'url':
        url();
        break;
    case 'about':
        about();
        break;
    default:
        showList();
}

// Показать список полей
function showList()
{
    global $sectionID;
    if (in_array($sectionID, ['grp.news', 'grp.users'])) {
        showSectionList();
    } else {
        showFieldList();
    }
}

function about(){
	global $twig, $lang, $breadcrumb, $extras, $sectionID;

	$tpath = locatePluginTemplates(array('config/main', 'config/about'), 'xfields', 1);
	$breadcrumb = breadcrumb('<i class="fa fa-list-alt btn-position"></i><span class="text-semibold">'.$lang['xfconfig']['custom_fields'].'</span>', array('?mod=extras' => '<i class="fa fa-puzzle-piece btn-position"></i>'.$lang['extras'].'', '?mod=extra-config&plugin=xfields&section='.$sectionID.'' => '<i class="fa fa-list-alt btn-position"></i>Дополнительные поля', '<i class="fa fa-exclamation-circle btn-position"></i>О плагине' ) );

	$tVars = array(
		'about' => $extras['xfields']['version'],
	);
	
	$xt = $twig->loadTemplate($tpath['config/about'].'config/about.tpl');
	$xg = $twig->loadTemplate($tpath['config/main'].'config/main.tpl');

	$tVars = array(
		'active0' => 'active',
		'entries' 	=> $xt->render($tVars),
	);
	
	print $xg->render($tVars);
}

//
function showSectionList() {
    global $xf, $lang, $tpl, $twig, $sectionID, $breadcrumb;
	
	$tpath = locatePluginTemplates(array('config/main', 'config/groups'), 'xfields', 1);
	$breadcrumb = breadcrumb('<i class="fa fa-list-alt btn-position"></i><span class="text-semibold">'.$lang['xfconfig']['custom_fields'].'</span>', array('?mod=extras' => '<i class="fa fa-puzzle-piece btn-position"></i>'.$lang['extras'].'', '?mod=extra-config&plugin=xfields&section='.$sectionID.'' => '<i class="fa fa-list-alt btn-position"></i>Дополнительные поля', $lang['xfconfig']['section.'.$sectionID] ) );

    $output = '';
    //$output .= "<pre>".var_export($xf[$sectionID], true)."</pre>";
	
    $tVars = [
        'sectionID' => $sectionID,
        'section_name' => $lang['xfconfig']['section.'.$sectionID],
		'xfields' => $xf['news'],
		'global' => $lang['xfconfig']['section.'.$sectionID],
        'groups' => [],
    ];
	
    // Prepare data
    $groups = [];
    foreach ($xf['grp.news'] as $k => $v) {
        $tVars['groups'][$k] = [
            'title'   => $v['title'],
			'entries' => $v['entries'],
        ];
    }

    foreach (array('news', 'grp.news', 'users', 'grp.users', 'tdata') as $cID)
        $tVars['class'][$cID] = ($cID == $sectionID) ? 'active' : '';

    $tVars['json']['groups.config'] = json_encode($grpNews);
    $tVars['json']['fields.config'] = json_encode($xf['news']);
	
	$xt = $twig->loadTemplate($tpath['config/groups'].'config/groups.tpl');
	$xg = $twig->loadTemplate($tpath['config/main'].'config/main.tpl');

	$tVars = array(
		'sectionID' => $sectionID,
		'entries' 	=> $xt->render($tVars)
	);
	
	print $xg->render($tVars);
}

//
// Показать список доп. полей
function showFieldList() {
    global $xf, $lang, $twig, $sectionID, $breadcrumb;
	
	$tpath = locatePluginTemplates(array('config/main', 'config/config'), 'xfields', 1);
	$breadcrumb = breadcrumb('<i class="fa fa-list-alt btn-position"></i><span class="text-semibold">'.$lang['xfconfig']['custom_fields'].'</span>', array('?mod=extras' => '<i class="fa fa-puzzle-piece btn-position"></i>'.$lang['extras'].'', '?mod=extra-config&plugin=xfields&section='.$sectionID.'' => '<i class="fa fa-list-alt btn-position"></i>Дополнительные поля', $lang['xfconfig']['section.'.$sectionID] ) );

    $xEntries = [];
    $output = '';
    if (isset($xf[$sectionID]) and is_array($xf[$sectionID])) {

    foreach ($xf[$sectionID] as $id => $data) {
        $storage = '';
        if ($data['storage']) {
            $storage = '<br/><font color="red"><b>'.$data['db.type'].($data['db.len'] ? (' ('.$data['db.len'].')') : '').'</b> </font>';
        }

        $xEntry = [
            'name'     => $id,
            'title'    => $data['title'],
            'type'     => $lang['xfconfig']['type_'.$data['type']].$storage,
            'default'  => (($data['type'] == 'checkbox') ? ($data['default'] ? $lang['yesa'] : $lang['noa']) : ($data['default'])),
            'link'     => '?mod=extra-config&plugin=xfields&action=edit&section='.$sectionID.'&field='.$id,
            'linkup'   => '?mod=extra-config&plugin=xfields&action=update&subaction=up&section='.$sectionID.'&field='.$id,
            'linkdown' => '?mod=extra-config&plugin=xfields&action=update&subaction=down&section='.$sectionID.'&field='.$id,
            'linkdel'  => '?mod=extra-config&plugin=xfields&action=update&subaction=del&section='.$sectionID.'&field='.$id,
			'modal'    => print_modal_dialog($id, 'Удалить '.$id.'', 'Вы уверены, что хотите удалить ID поля - '.$id.' имя - '.$data['title'].'?', '<a href="?mod=extra-config&plugin=xfields&action=update&subaction=del&section='.$sectionID.'&field='.$id.'" class="btn btn-outline-success">да</a>'),
            'area'     => (intval($data['area']) > 0) ? intval($data['area']) : intval($data['area']),
			'larea'     => (intval($data['area']) > 0) ? $lang['xfconfig']['block_location1'] : $lang['xfconfig']['block_location0'],
            'extends' => $lang['extends_' . (!empty($data['extends']) ? $data['extends'] : 'additional')],
             'flags'    => [
                'required' => $data['required'] ? true : false,
                'default'  => (($data['default'] != '') || ($data['type'] == 'checkbox')) ? true : false,
                'disabled' => $data['disabled'] ? true : false,
                'regpage'  => $data['regpage'] ? true : false,
            ],
        ];
        $options = '';
        if (is_array($data['options']) && count($data['options'])) {
            foreach ($data['options'] as $k => $v) {
                $options .= (($data['storekeys']) ? ('<b>'.$k.'</b>: '.$v) : ('<b>'.$v.'</b>'))."<br>\n";
            }
        }
        $xEntry['options'] = $options;
        $xEntries[] = $xEntry;
        }
    }

    if (!count($xf[$sectionID])) {
        $output = $lang['xfconfig']['nof'];
    }
    $tVars = [
        'xfields'      => $xEntries,
        'section_name' => $lang['xfconfig']['section.'.$sectionID],
		'global' => $lang['xfconfig']['section.'.$sectionID],
        'sectionID'    => $sectionID,
    ];
	
    foreach (array('news', 'grp.news', 'users', 'grp.users', 'tdata') as $cID)
        $tVars['class'][$cID] = ($cID == $sectionID) ? 'active' : '';

	$xt = $twig->loadTemplate($tpath['config/config'].'config/config.tpl');
	$xg = $twig->loadTemplate($tpath['config/main'].'config/main.tpl');

	$tVars = array(
		'sectionID' => $sectionID,
		'entries' 	=> $xt->render($tVars)
	);
	
	print $xg->render($tVars);
}

//
//
function showAddEditForm($xdata = '', $eMode = null, $efield = null) {
    global $xf, $lang, $sectionID, $twig, $breadcrumb;
	
    $field = ($efield == null) ? $_REQUEST['field'] : $efield;
	
    if ($eMode == null) {
        $editMode = (is_array($xf[$sectionID][$field])) ? 1 : 0;
    } else {
        $editMode = $eMode;
    }
	
    $tVars = [];
	
    if ($editMode) {
        $data = is_array($xdata) ? $xdata : $xf[$sectionID][$field];
        $tVars['flags']['editMode'] = 1;
        $tVars['flags']['disabled'] = $data['disabled'] ? true : false;
        $tVars['flags']['regpage'] = $data['regpage'] ? true : false;
        $tVars = $tVars + [
            'id'           => $field,
            'title'        => $data['title'],
            'type'         => $data['type'],
            'storage'      => intval($data['storage']),
            'db_type'      => $data['db.type'],
            'db_len'       => (intval($data['db.len']) > 0) ? intval($data['db.len']) : '',
            /* 'area'         => (intval($data['area']) > 0) ? intval($data['area']) : '', */
			'main_selected' => ($data['area'] == '1') ? 'selected' : '',
			'additional_selected' => ($data['area'] == '0') ? 'selected' : '',
            'bb_support'   => $data['bb_support'] ? 'checked="checked"' : '',
            'html_support' => $data['html_support'] ? 'checked="checked"' : '',
			'xf_field'   => $data['xf_field'] ? 'checked="checked"' : '',
            'noformat'     => $data['noformat'] ? 'checked="checked"' : '',
        ];
		
        $xsel = '';
		
        foreach (['text', 'textarea', 'select', 'multiselect', 'checkbox', 'images'] as $ts) {
            $tVars['defaults'][$ts] = ($data['type'] == $ts) ? (($ts == 'checkbox') ? ($data['default'] ? ' checked="checked"' : '') : $data['default']) : '';
            $xsel .= '<option value="'.$ts.'"'.(($data['type'] == $ts) ? ' selected' : '').'>'.$lang['xfields_type_'.$ts];
        }
		
        $sOpts = [];
        $fNum = 1;
		
        if ($data['type'] == 'select') {
            if (is_array($data['options'])) {
                foreach ($data['options'] as $k => $v) {
                    array_push($sOpts, '<tr><td><input size="12" name="so_data['.($fNum).'][0]" type="text" value="'.($data['storekeys'] ? secure_html($k) : '').'"/></td><td><input type="text" size="55" name="so_data['.($fNum).'][1]" value="'.secure_html($v).'"/></td><td><a href="#" onclick="return false;"><img src="'.skins_url.'/images/delete.gif" alt="DEL" width="12" height="12" /></a></td></tr>');
                    $fNum++;
                }
            }
        }
		
        if (!count($sOpts)) {
            array_push($sOpts, '<tr><td><input size="12" name="so_data[1][0]" type="text" value=""/></td><td><input type="text" size="55" name="so_data[1][1]" value=""/></td><td><a href="#" onclick="return false;"><img src="'.skins_url.'/images/delete.gif" alt="DEL" width="12" height="12" /></a></td></tr>');
        }
		
        $m_sOpts = [];
        $fNum = 1;
		
        if ($data['type'] == 'multiselect') {
            if (is_array($data['options'])) {
                foreach ($data['options'] as $k => $v) {
                    array_push($m_sOpts, '<tr><td><input size="12" name="mso_data['.($fNum).'][0]" type="text" value="'.($data['storekeys'] ? secure_html($k) : '').'"/></td><td><input type="text" size="55" name="mso_data['.($fNum).'][1]" value="'.secure_html($v).'"/></td><td><a href="#" onclick="return false;"><img src="'.skins_url.'/images/delete.gif" alt="DEL" width="12" height="12" /></a></td></tr>');
                    $fNum++;
                }
            }
        }
		
        if (!count($m_sOpts)) {
            array_push($m_sOpts, '<tr><td><input size="12" name="mso_data[1][0]" type="text" value=""/></td><td><input type="text" size="55" name="mso_data[1][1]" value=""/></td><td><a href="#" onclick="return false;"><img src="'.skins_url.'/images/delete.gif" alt="DEL" width="12" height="12" /></a></td></tr>');
        }
		
        $tVars = $tVars + [
            'sOpts'          => implode("\n", $sOpts),
            'm_sOpts'        => implode("\n", $m_sOpts),
            'type_opts'      => $xsel,
            'storekeys_opts' => '<option value="0">Сохранять значение</option><option value="1"'.(($data['storekeys']) ? ' selected' : '').'>Сохранять код</option>',
            'required_opts'  => '<option value="0">Нет</option><option value="1"'.(($data['required']) ? ' selected' : '').'>Да</option>',
            'images'         => [
                'maxCount'    => intval($data['maxCount']),
                'thumbWidth'  => intval($data['thumbWidth']),
                'thumbHeight' => intval($data['thumbHeight']),
            ],
        ];
		
        foreach (['imgStamp', 'imgShadow', 'imgThumb', 'thumbStamp', 'thumbShadow'] as $k) {
            $tVars['images'][$k] = intval($data[$k]) ? 'checked="checked"' : '';
        }
        //print "<pre>".var_export($tVars, true)."</pre>";
    } else {
        $sOpts = [];
        array_push($sOpts, '<tr><td><input size="12" name="so_data[1][0]" type="text" value=""/></td><td><input type="text" size="55" name="so_data[1][1]" value=""/></td><td><a href="#" onclick="return false;"><img src="'.skins_url.'/images/delete.gif" alt="DEL" width="12" height="12" /></a></td></tr>');
        $m_sOpts = [];
        array_push($m_sOpts, '<tr><td><input size="12" name="mso_data[1][0]" type="text" value=""/></td><td><input type="text" size="55" name="mso_data[1][1]" value=""/></td><td><a href="#" onclick="return false;"><img src="'.skins_url.'/images/delete.gif" alt="DEL" width="12" height="12" /></a></td></tr>');
        $tVars['flags']['editmode'] = 0;
        $tVars['flags']['disabled'] = false;
        $tVars = $tVars + [
            'sOpts'   => implode("\n", $sOpts),
            'm_sOpts' => implode("\n", $m_sOpts),
            'id'      => '',
            'title'   => '',
            'type'    => 'text',
            'storage' => '0',
            'db_type' => '',
            'db_len'  => '',
        ];
		
        $xsel = '';
        foreach (['text', 'textarea', 'select', 'multiselect', 'checkbox', 'images'] as $ts) {
            $tVars['defaults'][$ts] = '';
            $xsel .= '<option value="'.$ts.'"'.(($data['type'] == 'text') ? ' selected' : '').'>'.$lang['xfields_type_'.$ts];
        }
		
        $tVars = $tVars + [
            'type_opts'      => $xsel,
            'storekeys_opts' => '<option value="0">Сохранять значение</option><option value="1">Сохранять код</option>',
            'required_opts'  => '<option value="0">Нет</option><option value="1">Да</option>',
            'select_options' => '',
            'images'         => [
                'maxCount'    => '1',
                'thumbWidth'  => '150',
                'thumbHeight' => '150',
            ],
        ];
		
        foreach (['imgStamp', 'imgShadow', 'imgThumb', 'thumbStamp', 'thumbShadow'] as $k) {
            $tVars['images'][$k] = '';
        }
    }
	
    $tVars['sectionID'] = $sectionID;

	if ($editMode) {
		$addedit = ''.$lang['xfields_title_edit'].' - '.$field.'';
	}else{
		$addedit = ''.$lang['xfields_title_add'].'';
	}

	$tpath = locatePluginTemplates(array('config/main', 'config/config_edit'), 'xfields', 1);
	$breadcrumb = breadcrumb('<i class="fa fa-list-alt btn-position"></i><span class="text-semibold">'.$lang['xfconfig']['custom_fields'].'</span>', array('?mod=extras' => '<i class="fa fa-puzzle-piece btn-position"></i>Управление плагинами', '?mod=extra-config&plugin=xfields&section='.$sectionID.'' => '<i class="fa fa-list-alt btn-position"></i>Дополнительные поля', $addedit ) );

	$xt = $twig->loadTemplate($tpath['config/config_edit'].'config/config_edit.tpl');
	$xg = $twig->loadTemplate($tpath['config/main'].'config/main.tpl');

	$tVars = array(
		'sectionID' => $sectionID,
		'entries' 	=> $xt->render($tVars)
	);
	
	print $xg->render($tVars);
}

//
//
function doAddEdit()
{
    global $xf, $XF, $lang, $tpl, $twig, $mysql, $sectionID;
    //print "<pre>".var_export($_POST, true)."</pre>";
    $error = 0;
    $field = $_REQUEST['id'];
    $editMode = $_REQUEST['edit'] ? 1 : 0;
    // Check if field exists or not [depends on mode]
    if ($editMode && (!is_array($xf[$sectionID][$field]))) {
        msg(['type' => 'error', 'text' => $lang['xfields_msge_noexists']]);
		return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_msge_noexists'], 'javascript:history.go(-1)' );
        $error = 1;
    } elseif (!$editMode && (is_array($xf[$sectionID][$field]))) {
        msg(['type' => 'error', 'text' => $lang['xfields_msge_exists']]);
		return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_msge_exists'], 'javascript:history.go(-1)' );
        $error = 1;
    }
    // Check if Field name fits our requirements
    if (!$editMode) {
        if (!preg_match('/^[a-z]{1}[a-z0-9]{2}[a-z0-9]*$/', $field)) {
            msg(['type' => 'error', 'text' => $lang['xfields_msge_format']]);
			return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_msge_format'], 'javascript:history.go(-1)' );
            $error = 1;
        }
    }
    // Let's fill parameters
    $data['title'] = $_REQUEST['title'];
    $data['required'] = isset($_REQUEST['required']) ? intval($_REQUEST['required']) : 0;
    $data['disabled'] = isset($_REQUEST['disabled']) ? intval($_REQUEST['disabled']) : 0;
    $data['area'] = intval($_REQUEST['area']);
	$data['extends'] = isset($_REQUEST['extends']) ? $_REQUEST['extends'] : 'additional';
    $data['type'] = $_REQUEST['type'];
    $data['bb_support'] = $_REQUEST['bb_support'] ? 1 : 0;
	$data['xf_field'] = $_REQUEST['xf_field'] ? 1 : 0;
    $data['default'] = '';
    if (($sectionID == 'users') && ($data['type'] != 'images'))
        $data['regpage'] = intval($_REQUEST['regpage']);

    switch ($data['type']) {
        case 'checkbox':
            $data['default'] = $_REQUEST['checkbox_default'] ? 1 : 0;
            break;
        case 'text':
            if ($_REQUEST['text_default'] != '') {
                $data['default'] = $_REQUEST['text_default'];
            }
            $data['bb_support'] = $_REQUEST['text_bb_support'] ? 1 : 0;
            $data['html_support'] = $_REQUEST['text_html_support'] ? 1 : 0;
			$data['xf_field'] = $_REQUEST['text_xf_field'] ? 1 : 0;
            break;
        case 'textarea':
            if ($_REQUEST['textarea_default'] != '') {
                $data['default'] = $_REQUEST['textarea_default'];
            }
            $data['bb_support'] = $_REQUEST['textarea_bb_support'] ? 1 : 0;
            $data['html_support'] = $_REQUEST['textarea_html_support'] ? 1 : 0;
            $data['noformat'] = $_REQUEST['textarea_noformat'] ? 1 : 0;
            break;
        case 'select':
            // Check options
            $optlist = [];
            $optvals = [];
            if (isset($_REQUEST['so_data']) && is_array($_REQUEST['so_data'])) {
                foreach ($_REQUEST['so_data'] as $k => $v) {
                    if (is_array($v) && isset($v[0]) && isset($v[1]) && (($v[0] != '') || ($v[1] != ''))) {
                        if ($v[0] != '') {
                            $optlist[$v[0]] = $v[1];
                        } else {
                            $optlist[] = $v[1];
                        }
                        //print "<pre>SO_LINE: ".$v[0].", ".$v[1]."</pre>";
                    }
                }
            }
            $opt_vals = array_values($optlist);
            /*
            $opts = $_REQUEST['select_options'];
            $optlist = array();
            $optvals = array();
            foreach (explode("\n", $opts) as $line) {
                $line = trim($line);
                if (preg_match('/^(.+?) *\=\> *(.+?)$/', $line, $match)) {
                    $optlist[$match[1]] = $match[2];
                    $optvals[$match[2]] = 1;
                } elseif ($line != '') {
                    $optlist[] = $line;
                    $optvals[$line] = 1;
                }
            }
            */
            $data['storekeys'] = intval($_REQUEST['select_storekeys']) ? 1 : 0;
            $data['options'] = $optlist;
            if (trim($_REQUEST['select_default'])) {
                $data['default'] = trim($_REQUEST['select_default']);
                if (
                    (($data['storekeys']) && (!array_key_exists($data['default'], $optlist))) ||
                    ((!$data['storekeys']) && (!in_array($data['default'], $optlist)))
                ) {
                    msg(['type' => 'error', 'text' => $lang['xfields_msge_errdefault']]);
					return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_msge_errdefault'], 'javascript:history.go(-1)' );
                    $error = 1;
                }
            }
            break;
        case 'multiselect':
            // Check options
            $optlist = [];
            $optvals = [];
            if (isset($_REQUEST['mso_data']) && is_array($_REQUEST['mso_data'])) {
                foreach ($_REQUEST['mso_data'] as $k => $v) {
                    if (is_array($v) && isset($v[0]) && isset($v[1]) && (($v[0] != '') || ($v[1] != ''))) {
                        if ($v[0] != '') {
                            $optlist[$v[0]] = $v[1];
                        } else {
                            $optlist[] = $v[1];
                        }
                        //print "<pre>SO_LINE: ".$v[0].", ".$v[1]."</pre>";
                    }
                }
            }
            $opt_vals = array_values($optlist);
            /*
            $opts = $_REQUEST['select_options'];
            $optlist = array();
            $optvals = array();
            foreach (explode("\n", $opts) as $line) {
                $line = trim($line);
                if (preg_match('/^(.+?) *\=\> *(.+?)$/', $line, $match)) {
                    $optlist[$match[1]] = $match[2];
                    $optvals[$match[2]] = 1;
                } elseif ($line != '') {
                    $optlist[] = $line;
                    $optvals[$line] = 1;
                }
            }
            */
            $data['storekeys'] = intval($_REQUEST['select_storekeys_multi']) ? 1 : 0;
            $data['options'] = $optlist;
            if (trim($_REQUEST['select_default_multi'])) {
                $data['default'] = trim($_REQUEST['select_default_multi']);
                if (
                    (($data['storekeys']) && (!array_key_exists($data['default'], $optlist))) ||
                    ((!$data['storekeys']) && (!in_array($data['default'], $optlist)))
                ) {
                    msg(['type' => 'error', 'text' => $lang['xfields_msge_errdefault']]);
					return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_msge_errdefault'], 'javascript:history.go(-1)' );
                    $error = 1;
                }
            }
            break;
        case 'images':
            $data['maxCount'] = intval($_REQUEST['images_maxCount']);
            $data['imgShadow'] = intval($_REQUEST['images_imgShadow']) ? 1 : 0;
            $data['imgStamp'] = intval($_REQUEST['images_imgStamp']) ? 1 : 0;
            $data['imgThumb'] = intval($_REQUEST['images_imgThumb']) ? 1 : 0;
            $data['thumbWidth'] = intval($_REQUEST['images_thumbWidth']);
            $data['thumbHeight'] = intval($_REQUEST['images_thumbHeight']);
            $data['thumbStamp'] = intval($_REQUEST['images_thumbStamp']) ? 1 : 0;
            $data['thumbShadow'] = intval($_REQUEST['images_thumbShadow']) ? 1 : 0;
            break;
        default:
            $data['type'] = '';
            break;
    }
    if (!$data['type']) {
        msg(['type' => 'error', 'text' => $lang['xfields_msge_errtype']]);
		return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_msge_errtype'], 'javascript:history.go(-1)' );
        $error = 1;
    }
    if (!$data['title']) {
        msg(['type' => 'error', 'text' => $lang['xfields_msge_errtitle']]);
		return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_msge_errtitle'], 'javascript:history.go(-1)' );
        $error = 1;
    }
    // Check for storage params
    $data['storage'] = $_REQUEST['storage'];
    $data['db.type'] = $_REQUEST['db_type'];
    $data['db.len'] = intval($_REQUEST['db_len']);
    if ($data['storage']) {
        // Check for correct DB type
        if (!in_array($data['db.type'], ['int', 'decimal', 'char', 'datetime', 'text'])) {
            msg(['type' => 'error', 'text' => $lang['xfields_error.db.type']]);
			return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_error.db.type'], 'javascript:history.go(-1)' );
            $error = 1;
        }
        // Check for correct DB len (if applicable)
        if (($data['db.type'] == 'char') && ((intval($data['db.len']) < 1) || (intval($data['db.len']) > 255))) {
            msg(['type' => 'error', 'text' => $lang['xfields_error.db.len']]);
			return print_msg( 'error', $lang['xfconfig']['custom_fields'], $lang['xfields_error.db.len'], 'javascript:history.go(-1)' );
            $error = 1;
        }
    }
    if ($error) {
        showAddEditForm($data, $editMode, $field, $sectionID);

        return;
    }
    $DB = [];
    $DB['new'] = ['storage' => $data['storage'], 'db.type' => $data['db.type'], 'db.len' => $data['db.len']];
    if ($editMode) {
        $DB['old'] = ['storage' => $XF[$sectionID][$field]['storage'], 'db.type' => $XF[$sectionID][$field]['db.type'], 'db.len' => $XF[$sectionID][$field]['db.len']];
    }
    $XF[$sectionID][$field] = $data;
    if (!xf_configSave()) {
        msg(['type' => 'error', 'text' => $lang['xfields_msge_errcsave']]);
        showAddEditForm($data, $editMode, $field);

        return;
    }
    // Now we should update table `_news` structure and content
    if (!($tableName = xf_getTableBySectionID($sectionID))) {
        echo 'Ошибка: неизвестная секция/блок ('.$sectionID.')';

        return;
    }
    $found = 0;
    foreach ($mysql->select('describe '.$tableName, 1) as $row) {
        if ($row['Field'] == 'xfields_'.$field) {
            $found = 1;
            break;
        }
    }
    $dbFlagChanged = 0;
    // 1. If we add XFIELD and field already exists in DB - drop it!
    // 2. If we don't want to store data in separate field - drop it!
    if ($found && (!$editMode || !$DB['new']['storage'])) {
        $mysql->query('alter table '.$tableName.' drop column `xfields_'.$field.'`');
    }
    // If we need to have this field - let's make it. But only if smth was changed
    do {
        if (!$data['storage']) {
            break;
        }
        // Anything should be done only if field is changed
        if (($DB['old']['db.type'] == $DB['new']['db.type']) && ($DB['old']['db.len'] == $DB['new']['db.len'])) {
            break;
        }
        $ftype = '';
        switch ($DB['new']['db.type']) {
            case 'int':
                $ftype = 'int';
                break;
            case 'decimal':
                $ftype = 'decimal (12,2)';
                break;
            case 'datetime':
                $ftype = 'datetime';
                break;
            case 'char':
                if (($DB['new']['db.len'] > 0) && ($DB['new']['db.len'] <= 255)) {
                    $ftype = 'char('.intval($DB['new']['db.len']).')';
                    break;
                }
            case 'text':
                $ftype = 'text';
                break;
        }
        if ($ftype) {
            $dbFlagChanged = 1;
            if ($found) {
                $mysql->query('alter table '.$tableName.' change column `xfields_'.$field.'` `xfields_'.$field.'` '.$ftype);
                $mysql->query('update '.$tableName.' set `xfields_'.$field.'` = NULL');
            } else {
                $mysql->query('alter table '.$tableName.' add column `xfields_'.$field.'` '.$ftype);
            }
        }
    } while (0);
    // Second - fill field's content if required
    if ($DB['new']['storage'] && $dbFlagChanged) {
        // Make updates with chunks for 500 RECS
        $recCount = 0;
        $maxID = 0;
        do {
            $recCount = 0;
            foreach ($mysql->select('select id, xfields from '.$tableName.' where (id > '.$maxID.") and (xfields is not NULL) and (xfields <> '') order by id limit 500") as $rec) {
                $recCount++;
                if ($rec['id'] > $maxID) {
                    $maxID = $rec['id'];
                }
                $xlist = xf_decode($rec['xfields']);
                if (isset($xlist[$field]) && ($xlist[$field] != '')) {
                    $mysql->query('update '.$tableName.' set `xfields_'.$field.'` = '.db_squote($xlist[$field]).' where id = '.db_squote($rec['id']));
                }
            }
        } while ($recCount);
    }
	
    $tVars = [
        'id'        => $field,
        'sectionID' => $sectionID,
        'flags'     => [
            'editMode' => $editMode ? true : false,
        ],
    ];

    $tVars['sectionID'] = $sectionID;

	if ($editMode){
		print_msg( 'update', $lang['xfconfig']['custom_fields'], 'Поле <b>'.$field.'</b> успешно было отредактировано!<br>Вы можете сделать следющее.', array('?mod=extra-config&plugin=xfields&action=add&section='.$sectionID.'' => 'Добавить еще', '?mod=extra-config&plugin=xfields&action=edit&section='.$sectionID.'&field='.$field => 'Редактировать еще', '?mod=extra-config&plugin=xfields&section='.$sectionID.'' => 'Вернуться назад' ) );
	}else{
		print_msg( 'success', $lang['xfconfig']['custom_fields'], 'Новое поле <b>'.$field.'</b> успешно было добавлено!<br>Вы можете сделать следющее.', array('?mod=extra-config&plugin=xfields&action=add&section='.$sectionID.'' => 'Добавить еще', '?mod=extra-config&plugin=xfields&action=edit&section='.$sectionID.'&field='.$field => 'Редактировать еще', '?mod=extra-config&plugin=xfields&section='.$sectionID.'' => 'Вернуться назад' ) );
	}

}

//
//
function doUpdate()
{
    global $xf, $XF, $lang, $tpl, $mysql, $sectionID, $notif;
    $error = 0;
    $field = $_REQUEST['field'];
    // Check if field exists or not [depends on mode]
    if (!is_array($xf[$sectionID][$field])) {
        msg(['type' => 'error', 'text' => $lang['xfields_msge_noexists'].'('.$sectionID.': '.$field.')']);
		return print_msg( 'error', $lang['xfconfig']['custom_fields'], ''.$lang['xfields_msge_noexists'].' ('.$sectionID.': '.$field.').', 'javascript:history.go(-1)' );
        $error = 1;
    }
    $notif = '';
    switch ($_REQUEST['subaction']) {
        case 'del':        // Delete field from SQL table if required
            if (($XF[$sectionID][$field]['storage']) && ($tableName = xf_getTableBySectionID($sectionID))) {
                // Check if field really exist
                $found = 0;
                foreach ($mysql->select('describe '.$tableName, 1) as $row) {
                    if ($row['Field'] == 'xfields_'.$field) {
                        $found = 1;
                        break;
                    }
                }
                if ($found) {
                    $mysql->query('alter table '.$tableName.' drop column `xfields_'.$field.'`');
                }
            }
            unset($XF[$sectionID][$field]);
            $notif = $lang['xfields_done_del'];
			print_msg( 'delete', $lang['xfconfig']['custom_fields'], 'Поле '.$field.' успешно было удалено!', array('?mod=extra-config&plugin=xfields&action=add&section='.$sectionID.'' => 'Добавить еще', '?mod=extra-config&plugin=xfields&section='.$sectionID.'' => 'Вернуться назад' ) );
            break;
        case 'up':
            array_key_move($XF[$sectionID], $field, -1);
            $notif = $lang['xfields_done_up'];
			showList();
            break;
        case 'down':
            array_key_move($XF[$sectionID], $field, 1);
            $notif = $lang['xfields_done_down'];
			showList();
            break;
        default:
            $notif = $lang['xfields_updateunk'];
			print_msg( 'warning', $lang['xfconfig']['custom_fields'], $lang['xfields_updateunk'], 'javascript:history.go(-1)' );
    }

    if (!xf_configSave() or $error) {
        msg(['type' => 'error', 'text' => $lang['xfields_msge_errcsave']]);
        return;
    } else {
        msg(array('text' => $notif));
    }

    $xf = $XF;
}

function array_key_move(&$arr, $key, $offset)
{
    $keys = array_keys($arr);
    $index = -1;
    foreach ($keys as $k => $v) {
        if ($v == $key) {
            $index = $k;
            break;
        }
    }
    if ($index == -1) {
        return 0;
    }
    $index2 = $index + $offset;
    if ($index2 < 0) {
        $index2 = 0;
    }
    if ($index2 > (count($arr) - 1)) {
        $index2 = count($arr) - 1;
    }
    if ($index == $index2) {
        return 1;
    }
    $a = min($index, $index2);
    $b = max($index, $index2);
    $arr = array_slice($arr, 0, $a) +
        array_slice($arr, $b, 1) +
        array_slice($arr, $a + 1, $b - $a) +
        array_slice($arr, $a, 1) +
        array_slice($arr, $b, count($arr) - $b);
}

function url(){
global $twig, $tpl, $mysql, $breadcrumb, $lang, $sectionID;
	
	$breadcrumb = breadcrumb('<i class="fa fa-list-alt btn-position"></i><span class="text-semibold">'.$lang['xfconfig']['custom_fields'].'</span>', array('?mod=extras' => '<i class="fa fa-puzzle-piece btn-position"></i>'.$lang['extras'].'', '?mod=extra-config&plugin=xfields&section='.$sectionID.'' => '<i class="fa fa-list-alt btn-position"></i>Дополнительные поля', '<i class="fa fa-random btn-position"></i>ЧПУ' ) );

	$tpath = locatePluginTemplates(array('config/main', 'config/url'), 'xfields', 1);
	
	if (isset($_REQUEST['submit'])) {
		if(isset($_REQUEST['url']) && !empty($_REQUEST['url'])) {
 			$ULIB = new urlLibrary();
			$ULIB->loadConfig();
			
			$ULIB->registerCommand('xfields', '',
				array ('vars' =>
						array( 'xf_id' => array('matchRegex' => '.+?', 'descr' => array('russian' => 'Имя поля')),
							   'page' => array('matchRegex' => '\d{1,4}', 'descr' => array('russian' => 'Постраничная навигация'))
						),
						'descr'	=> array ('russian' => 'Гиперссылки полей'),
				)
			);

			$ULIB->saveConfig();
			
			$UHANDLER = new urlHandler();
			$UHANDLER->loadConfig();
		
			$UHANDLER->registerHandler(0,
				array (
				'pluginName' => 'xfields',
				'handlerName' => '',
				'flagPrimary' => true,
				'flagFailContinue' => false,
				'flagDisabled' => false,
				'rstyle' => 
				array (
				  'rcmd' => '/xfields/{xf_id}[/page/{page}].html',
				  'regex' => '#^/xfields/(.+?)(?:/page/(\\d{1,4})){0,1}.html$#',
				  'regexMap' => 
				  array (
				    1 => 'xf_id',
					2 => 'page',
				  ),
				  'reqCheck' => 
				  array (
				  ),
				  'setVars' => 
				  array (
				  ),
				  'genrMAP' => 
				  array (
					0 => 
					array (
					  0 => 0,
					  1 => '/xfields/',
					  2 => 0,
					),
					1 => 
					array (
					  0 => 1,
					  1 => 'xf_id',
					  2 => 0,
					),
					2 => 
					array (
					  0 => 0,
					  1 => '/page/',
					  2 => 1,
					),
					3 => 
					array (
					  0 => 1,
					  1 => 'page',
					  2 => 1,
					),
					4 => 
					array (
					  0 => 0,
					  1 => '.html',
					  2 => 0,
					),
				  ),
				),
			  )
			);

			$UHANDLER->saveConfig();
		} else {
			$ULIB = new urlLibrary();
			$ULIB->loadConfig();
			$ULIB->removeCommand('xfields', '');
			$ULIB->saveConfig();
			$UHANDLER = new urlHandler();
			$UHANDLER->loadConfig();
			$UHANDLER->removePluginHandlers('xfields', '');
			$UHANDLER->saveConfig();
		}
		
		pluginSetVariable('xfields', 'url', intval($_REQUEST['url']));
		pluginsSaveConfig();
		
		return print_msg( 'info', $lang['xfconfig']['custom_fields'], 'Настройки успешно сохранены!', 'javascript:history.go(-1)' );
	}
	
	$url = pluginGetVariable('xfields', 'url');
	$url = '<option value="0" '.(empty($url)?'selected':'').'>Нет</option><option value="1" '.(!empty($url)?'selected':'').'>Да</option>';

	$chpu = 'Перейдите в раздел <a href="admin.php?mod=rewrite">формат ссылок</a> после активации';

	$tVars = array(
		'info' => $url,
		'header' 	=> $chpu,
		'panel' 	=> 'Управления ЧПУ плагина',
	);
	
	$xt = $twig->loadTemplate($tpath['config/url'].'config/url.tpl');
	$xg = $twig->loadTemplate($tpath['config/main'].'config/main.tpl');

	$tVars = array(
		'active1' => 'active',
		'entries' 	=> $xt->render($tVars),
	);
	
	print $xg->render($tVars);
}