<?php

// #==========================================================#
// # Plugin name: xfields [ Additional fields managment ]     #
// # Author: Vitaly A Ponomarev, vp7@mail.ru                  #
// # Allowed to use only with: Next Generation CMS            #
// #==========================================================#
// Protect against hack attempts
if (!defined('NGCMS')) {
    die('HAL');
}
// Load lang files
LoadPluginLang('xfields', 'config');
LoadPluginLibrary('xfields', 'common');
//
// XFields: Add/Modify attached files
function xf_modifyAttachedImages($dsID, $newsID, $xf, $attachList)
{
    global $mysql, $config, $DSlist;
    //print "<pre>".var_export($_REQUEST, true)."</pre>";
    // Init file/image processing libraries
    $fmanager = new file_managment();
    $imanager = new image_managment();
    // Select xf group name
    $xfGroupName = '';
    foreach (['news', 'users'] as $k) {
        if ($DSlist[$k] == $dsID) {
            $xfGroupName = $k;
            break;
        }
    }
    if (!$xfGroupName) {
        return false;
    }
    // Scan if user want to change description
    foreach ($attachList as $iRec) {
        //print "[A:".$iRec['id']."]";
        if (isset($_REQUEST['xfields_'.$iRec['pidentity'].'_dscr']) && is_array($_REQUEST['xfields_'.$iRec['pidentity'].'_dscr']) && isset($_REQUEST['xfields_'.$iRec['pidentity'].'_dscr'][$iRec['id']])) {
            // We have this field in EDIT mode
            if ($_REQUEST['xfields_'.$iRec['pidentity'].'_dscr'][$iRec['id']] != $iRec['decsription']) {
                $mysql->query('update '.prefix.'_images set description = '.db_squote($_REQUEST['xfields_'.$iRec['pidentity'].'_dscr'][$iRec['id']]).' where id = '.intval($iRec['id']));
            }
        }
    }
    $xdata = [];
    foreach ($xf[$xfGroupName] as $id => $data) {
        // Attached images are processed in special way
        if ($data['type'] == 'images') {
            // Check if we should delete some images
            if (isset($_POST['xfields_'.$id.'_del']) && is_array($_POST['xfields_'.$id.'_del'])) {
                foreach ($_POST['xfields_'.$id.'_del'] as $key => $value) {
                    // Allow to delete only images, that are attached to current news
                    if ($value) {
                        $xf = false;
                        foreach ($attachList as $irow) {
                            if ($irow['id'] == $key) {
                                $xf = true;
                                break;
                            }
                        }
                        if (!$xf) {
                            continue;
                        }
                        //print "NEED TO DEL [$key]<br/>\n";
                        $fmanager->file_delete(['type' => 'image', 'id' => $key]);
                    }
                }
            }
            // Check for new attached files
            if (isset($_FILES['xfields_'.$id]) && isset($_FILES['xfields_'.$id]['name']) && is_array($_FILES['xfields_'.$id]['name'])) {
                foreach ($_FILES['xfields_'.$id]['name'] as $iId => $iName) {
                    if ($_FILES['xfields_'.$id]['error'][$iId] > 0) {
                        //print $iId." >>ERROR: ".$_FILES['xfields_'.$id]['error'][$iId]."<br/>\n";
                        continue;
                    }
                    if ($_FILES['xfields_'.$id]['size'][$iId] == 0) {
                        //print $iId." >>EMPTY IMAGE<br/>\n";
                        continue;
                    }
                    // Check if we try to overcome limits
                    $currCount = $mysql->record('select count(*) as cnt from '.prefix.'_images where (linked_ds = '.intval($dsID).') and (linked_id = '.intval($newsID).") and (plugin = 'xfields') and (pidentity=".db_squote($id).')');
                    if ($currCount['cnt'] >= $data['maxCount']) {
                        continue;
                    }
                    // Upload file
                    $up = $fmanager->file_upload(
                        [
                            'dsn'         => true,
                            'linked_ds'   => $dsID,
                            'linked_id'   => $newsID,
                            'type'        => 'image',
                            'http_var'    => 'xfields_'.$id,
                            'http_varnum' => $iId,
                            'plugin'      => 'xfields',
                            'pidentity'   => $id,
                            'description' => (isset($_REQUEST['xfields_'.$id.'_adscr']) && is_array($_REQUEST['xfields_'.$id.'_adscr']) && isset($_REQUEST['xfields_'.$id.'_adscr'][$iId])) ? ($_REQUEST['xfields_'.$id.'_adscr'][$iId]) : '',
                        ]
                    );
                    // Process upload error
                    if (!is_array($up)) {
                        continue;
                    }
                    //print "<pre>CREATED: ".var_export($up, true)."</pre>";
                    // Check if we need to create preview
                    $mkThumb = $data['imgThumb'];
                    $mkStamp = $data['imgStamp'];
                    $mkShadow = $data['imgShadow'];
                    $stampFileName = '';
                    if (file_exists(root.'trash/'.$config['wm_image'].'.gif')) {
                        $stampFileName = root.'trash/'.$config['wm_image'].'.gif';
                    } elseif (file_exists(root.'trash/'.$config['wm_image'])) {
                        $stampFileName = root.'trash/'.$config['wm_image'];
                    }
                    if ($mkThumb) {
                        // Calculate sizes
                        $tsx = $data['thumbWidth'];
                        $tsy = $data['thumbHeight'];
                        if ($tsx < 10) {
                            $tsx = 150;
                        }
                        if ($tsy < 10) {
                            $tsy = 150;
                        }
                        $thumb = $imanager->create_thumb($config['attach_dir'].$up[2], $up[1], $tsx, $tsy, $config['thumb_quality']);
                        //print "<pre>THUMB: ".var_export($thumb, true)."</pre>";
                        if ($thumb) {
                            //print "THUMB_OK<br/>";
                            // If we created thumb - check if we need to transform it
                            $stampThumb = ($data['thumbStamp'] && ($stampFileName != '')) ? 1 : 0;
                            $shadowThumb = $data['thumbShadow'];
                            if ($shadowThumb || $stampThumb) {
                                $stamp = $imanager->image_transform(
                                    [
                                        'image'              => $config['attach_dir'].$up[2].'/thumb/'.$up[1],
                                        'stamp'              => $stampThumb,
                                        'stamp_transparency' => $config['wm_image_transition'],
                                        'stamp_noerror'      => true,
                                        'shadow'             => $shadowThumb,
                                        'stampfile'          => $stampFileName,
                                    ]
                                );
                                //print "THUMB [STAMP/SHADOW = (".$stamp.")]<br/>";
                            }
                        }
                    }
                    if ($mkStamp || $mkShadow) {
                        $stamp = $imanager->image_transform(
                            [
                                'image'              => $config['attach_dir'].$up[2].'/'.$up[1],
                                'stamp'              => $mkStamp,
                                'stamp_transparency' => $config['wm_image_transition'],
                                'stamp_noerror'      => true,
                                'shadow'             => $mkShadow,
                                'stampfile'          => $stampFileName,
                            ]
                        );
                        //print "IMG [STAMP/SHADOW = (".var_export($stamp, true).")]<br/>";
                    }
                    // Now write info about image into DB
                    if (is_array($sz = $imanager->get_size($config['attach_dir'].$up[2].'/'.$up[1]))) {
                        $fmanager->get_limits($type);
                        // Gather filesize for thumbinals
                        $thumb_size_x = 0;
                        $thumb_size_y = 0;
                        if (is_array($thumb) && is_readable($config['attach_dir'].$up[2].'/thumb/'.$up[1]) && is_array($szt = $imanager->get_size($config['attach_dir'].$up[2].'/thumb/'.$up[1]))) {
                            $thumb_size_x = $szt[1];
                            $thumb_size_y = $szt[2];
                        }
                        $mysql->query('update '.prefix.'_'.$fmanager->tname.' set width='.db_squote($sz[1]).', height='.db_squote($sz[2]).', preview='.db_squote(is_array($thumb) ? 1 : 0).', p_width='.db_squote($thumb_size_x).', p_height='.db_squote($thumb_size_y).', stamp='.db_squote(is_array($stamp) ? 1 : 0).' where id = '.db_squote($up[0]));
                    }
                }
            }
        }
    }
}

// Perform replacements while showing news
class XFieldsNewsFilter extends NewsFilter
{
    public function addNewsForm(&$tvars)
    {
        global $lang, $twig, $catz;
        // Load config
        $xf = xf_configLoad();
        if (!is_array($xf)) {
            return false;
        }
        $output = '';
        $xfEntries = [];
        $xfList = [];
        if (is_array($xf['news'])) {
            foreach ($xf['news'] as $id => $data) {
                if ($data['disabled']) {
                    continue;
                }
                $xfEntry = [
                    'title'        => $data['title'],
                    'id'           => $id,
                    'value'        => $xdata[$id],
                    'secure_value' => secure_html($xdata[$id]),
                    'data'         => $data,
                    'required'     => $lang['xfields_fld_'.($data['required'] ? 'required' : 'optional')],
                    'flags'        => [
                        'required' => $data['required'] ? true : false,
                    ],
                ];
                switch ($data['type']) {
                    case 'checkbox':
                        $val = '<input type="checkbox" id="form_xfields_'.$id.'" name="xfields['.$id.']" title="'.$data['title'].'" value="1" '.($data['default'] ? 'checked="checked"' : '').'"/>';
                        $xfEntry['input'] = $val;
                        break;
                    case 'text':
                        $val = '<input type="text" id="form_xfields_'.$id.'" name="xfields['.$id.']" title="'.$data['title'].'" value="'.secure_html($data['default']).'"/>';
                        $xfEntry['input'] = $val;
                        break;
                    case 'select':
                        $val = '<select name="xfields['.$id.']" id="form_xfields_'.$id.'" >';
                        if (!$data['required']) {
                            $val .= '<option value=""></option>';
                        }
                        if (is_array($data['options'])) {
                            foreach ($data['options'] as $k => $v) {
                                $val .= '<option value="'.secure_html(($data['storekeys']) ? $k : $v).'"'.((($data['storekeys'] && $data['default'] == $k) || (!$data['storekeys'] && $data['default'] == $v)) ? ' selected' : '').'>'.$v.'</option>';
                            }
                        }
                        $val .= '</select>';
                        $xfEntry['input'] = $val;
                        break;
                    case 'multiselect':
                        $val = '<select name="xfields['.$id.'][]" id="form_xfields_'.$id.'" multiple="multiple">';
                        if (!$data['required']) {
                            $val .= '<option value=""></option>';
                        }
                        if (is_array($data['options'])) {
                            foreach ($data['options'] as $k => $v) {
                                $val .= '<option value="'.secure_html(($data['storekeys']) ? $k : $v).'"'.((($data['storekeys'] && $data['default'] == $k) || (!$data['storekeys'] && $data['default'] == $v)) ? ' selected' : '').'>'.$v.'</option>';
                            }
                        }
                        $val .= '</select>';
                        $xfEntry['input'] = $val;
                        break;
                    case 'textarea':
                        $val = '<textarea cols="30" rows="5" name="xfields['.$id.']" id="form_xfields_'.$id.'" >'.$data['default'].'</textarea>';
                        $xfEntry['input'] = $val;
                        break;
                    case 'images':
                        $iCount = 0;
                        $input = '';
                        $tVars = ['images' => []];
                        // Show entries for allowed number of attaches
                        for ($i = $iCount + 1; $i <= intval($data['maxCount']); $i++) {
                            $tImage = [
                                'number' => $i,
                                'id'     => $id,
                                'flags'  => [
                                    'exist' => false,
                                ],
                            ];
                            $tVars['images'][] = $tImage;
                        }
                        // Make template
                        $xt = $twig->loadTemplate('plugins/xfields/tpl/ed_entry.image.tpl');
                        $val = $xt->render($tVars);
                        $xfEntry['input'] = $val;
                        break;
                    default:
                        continue(2);
                }
                $xfEntries[intval($data['area'])][] = $xfEntry;
                $xfList[$id] = $xfEntry;
            }
        }
        $xfCategories = [];
        foreach ($catz as $cId => $cData) {
            $xfCategories[$cData['id']] = $cData['xf_group'];
        }
        // Prepare table data [if needed]
        $flagTData = false;
        // Data are not provisioned
        $tlist = [];
        // Prepare config
        $tclist = [];
        $thlist = [];
        if (isset($xf['tdata']) && is_array($xf['tdata'])) {
            foreach ($xf['tdata'] as $fId => $fData) {
                if ($fData['disabled']) {
                    continue;
                }
                $flagTData = true;
                $tclist[$fId] = [
                    'title'    => $fData['title'],
                    'required' => $fData['required'],
                    'type'     => $fData['type'],
                    'default'  => $fData['default'],
                ];
                $thlist[] = [
                    'id'    => $fId,
                    'title' => $fData['title'],
                ];
                if ($fData['type'] == 'select') {
                    $tclist[$fId]['storekeys'] = $fData['storekeys'];
                    $tclist[$fId]['options'] = $fData['options'];
                }
            }
        }
        $xfNews = $xf['news'] ?: [];
        $tVars = [
            //	'entries'	=>	$xfEntries,
            'xfGC'       => json_encode($xf['grp.news']),
            'xfCat'      => json_encode($xfCategories),
            'xfList'     => json_encode(array_keys($xfNews)),
            'xtableConf' => json_encode($tclist),
            'xtableVal'  => isset($_POST['xftable']) ? $_POST['xftable'] : json_encode($tlist),
            'xtableHdr'  => $thlist,
            'xtablecnt'  => count($thlist),
            'flags'      => [
                'tdata' => $flagTData,
            ],
        ];
        if (!isset($xfEntries[0])) {
            $xfEntries[0] = [];
        }
        foreach ($xfEntries as $k => $v) {
            // Check if we have template for specific area, elsewhere - use basic [0] template
            $templateName = 'plugins/xfields/tpl/news.add.'.(file_exists(root.'plugins/xfields/tpl/news.add.'.$k.'.tpl') ? $k : '0').'.tpl';
            $xt = $twig->loadTemplate($templateName);
            $tVars['entries'] = $v;
            $tVars['entryCount'] = count($v);
            $tVars['area'] = $k;
            // Table data is available only for area 0
            $tVars['flags']['tdata'] = (!$k) ? $flagTData : 0;
            // Render block
            $tvars['plugin']['xfields'][$k] .= $xt->render($tVars);
        }
        unset($tVars['entries']);
        unset($tVars['area']);
        // Render general part [with JavaScript]
        $xt = $twig->loadTemplate('plugins/xfields/tpl/news.general.tpl');
        $tvars['plugin']['xfields']['general'] = $xt->render($tVars);
        $tvars['plugin']['xfields']['fields'] = $xfList;

        return 1;
    }

    public function addNews(&$tvars, &$SQL)
    {
        global $lang, $twig, $twigLoader;
        // Load config
        $xf = xf_configLoad();
        if (!is_array($xf)) {
            return 1;
        }
        $rcall = $_REQUEST['xfields'];
        if (!is_array($rcall)) {
            $rcall = [];
        }
        $xdata = [];
        foreach ($xf['news'] as $id => $data) {
            if ($data['disabled']) {
                continue;
            }
            if ($data['type'] == 'images') {
                continue;
            }
            // Fill xfields. Check that all required fields are filled
            if ($rcall[$id] != '') {
                $xdata[$id] = $rcall[$id];
            } elseif ($data['required']) {
                msg(['type' => 'error', 'text' => $id, 'info' => str_replace('{field}', $id, $lang['xfields_msge_emptyrequired'])]);

                return 0;
            }
            // Check if we should save data into separate SQL field
            if ($data['storage'] && ($rcall[$id] != '')) {
                $SQL['xfields_'.$id] = $rcall[$id];
            }
        }
        //var_dump($xdata);
        $SQL['xfields'] = xf_encode($xdata);

        return 1;
    }

    public function addNewsNotify(&$tvars, $SQL, $newsID)
    {
        global $mysql;
        // Load config
        $xf = xf_configLoad();
        if (!is_array($xf)) {
            return 1;
        }
        xf_modifyAttachedImages(1, $newsID, $xf, []);
        // Scan fields and check if we have attached images for fields with type 'images'
        $haveImages = false;
        foreach ($xf['news'] as $fid => $fval) {
            if ($fval['type'] == 'images') {
                $haveImages = true;
                break;
            }
        }
        if ($haveImages) {
            // Get real ID's of attached images and print here
            $idlist = [];
            foreach ($mysql->select('select id, plugin, pidentity from '.prefix.'_images where (linked_ds = 1) and (linked_id = '.db_squote($newsID).')') as $irec) {
                if ($irec['plugin'] == 'xfields') {
                    $idlist[$irec['pidentity']][] = $irec['id'];
                }
            }
            // Decode xfields
            $xdata = xf_decode($SQL['xfields']);
            //print "<pre>IDLIST: ".var_export($idlist, tru)."</pre>";
            // Scan for fields that should be configured to have attached images
            foreach ($xf['news'] as $fid => $fval) {
                if (($fval['type'] == 'images') && (isset($idlist[$fid]))) {
                    $xdata[$fid] = implode(',', $idlist[$fid]);
                }
            }
            $mysql->query('update '.prefix.'_news set xfields = '.db_squote(xf_encode($xdata)).' where id = '.db_squote($newsID));
        }
        // Prepare table data [if needed]
        if (isset($xf['tdata']) && is_array($xf['tdata']) && isset($_POST['xftable']) && is_array($xft = json_decode($_POST['xftable'], true))) {

            //print "<pre>[".(is_array($xft)?'ARR':'NOARR')."]INCOMING ARRAY: ".var_export($xft, true)."</pre>";
            $recList = [];
            $queryList = [];
            // SCAN records
            foreach ($xft as $k => $v) {
                if (is_array($v) && isset($v['#id'])) {
                    $editMode = 0;
                    $tRec = ['xfields' => []];
                    foreach ($xf['tdata'] as $fId => $fData) {
                        if ($fData['storage']) {
                            $tRec['xfields_'.$fId] = db_squote($v[$fId]);
                        }
                        $tRec['xfields'][$fId] = $v[$fId];
                    }
                    $tRec['xfields'] = db_squote(serialize($tRec['xfields']));
                    // Now update record info
                    $query = 'insert into '.prefix.'_xfields ('.implode(', ', array_keys($tRec)).', linked_ds, linked_id) values ('.implode(', ', array_values($tRec)).', 1, '.(intval($newsID)).')';
                    //print "SQL: $query <br/>\n";
                    $queryList[] = $query;
                    //$mysql->query($query);
                    //print "GOT LINE:<pre>".var_export($tRec, true)."</pre>";
                }
            }
            // Execute queries
            foreach ($queryList as $query) {
                $mysql->query($query);
            }
        }

        return 1;
    }

    public function editNewsForm($newsID, $SQLold, &$tvars)
    {
        global $lang, $catz, $mysql, $config, $twig, $twigLoader;
        //print "<pre>".var_export($lang, true)."</pre>";
        // Load config
        $xf = xf_configLoad();
        if (!is_array($xf)) {
            return false;
        }
        // Fetch xfields data
        $xdata = xf_decode($SQLold['xfields']);
        if (!is_array($xdata)) {
            return false;
        }
        $output = '';
        $xfEntries = [];
        foreach ($xf['news'] as $id => $data) {
            if ($data['disabled']) {
                continue;
            }
            $xfEntry = [
                'title'    => $data['title'],
                'id'       => $id,
                'required' => $lang['xfields_fld_'.($data['required'] ? 'required' : 'optional')],
                'flags'    => [
                    'required' => $data['required'] ? true : false,
                ],
            ];
            switch ($data['type']) {
                case 'checkbox':
                    $val = '<input type="checkbox" id="form_xfields_'.$id.'" name="xfields['.$id.']" title="'.$data['title'].'" value="1" '.($xdata[$id] ? 'checked="checked"' : '').'"/>';
                    $xfEntry['input'] = $val;
                    $xfEntries[intval($data['area'])][] = $xfEntry;
                    break;
                case 'text':
                    $val = '<input type="text" name="xfields['.$id.']"  id="form_xfields_'.$id.'" title="'.$data['title'].'" value="'.secure_html($xdata[$id]).'" />';
                    $xfEntry['input'] = $val;
                    $xfEntries[intval($data['area'])][] = $xfEntry;
                    break;
                case 'select':
                    $val = '<select name="xfields['.$id.']" id="form_xfields_'.$id.'" >';
                    if (!$data['required']) {
                        $val .= '<option value="">&nbsp;</option>';
                    }
                    if (is_array($data['options'])) {
                        foreach ($data['options'] as $k => $v) {
                            $val .= '<option value="'.secure_html(($data['storekeys']) ? $k : $v).'"'.((($data['storekeys'] && ($xdata[$id] == $k)) || (!$data['storekeys'] && ($xdata[$id] == $v))) ? ' selected' : '').'>'.$v.'</option>';
                        }
                    }
                    $val .= '</select>';
                    $xfEntry['input'] = $val;
                    $xfEntries[intval($data['area'])][] = $xfEntry;
                    break;
                case 'multiselect':
                    $val = '<select name="xfields['.$id.'][]" id="form_xfields_'.$id.'" multiple="multiple">';
                    if (!$data['required']) {
                        $val .= '<option value="">&nbsp;</option>';
                    }
                    if (is_array($data['options'])) {
                        foreach ($data['options'] as $k => $v) {
                            var_dump();
                            $val .= '<option value="'.secure_html(($data['storekeys']) ? $k : $v).'"'.((($data['storekeys'] && (in_array($k, $xdata[$id]))) || (!$data['storekeys'] && (in_array($v, $xdata[$id])))) ? ' selected' : '').'>'.$v.'</option>';
                        }
                    }
                    $val .= '</select>';
                    $xfEntry['input'] = $val;
                    $xfEntries[intval($data['area'])][] = $xfEntry;
                    break;
                case 'textarea':
                    $val = '<textarea cols="30" rows="4" name="xfields['.$id.']" id="form_xfields_'.$id.'">'.$xdata[$id].'</textarea>';
                    $xfEntry['input'] = $val;
                    $xfEntries[intval($data['area'])][] = $xfEntry;
                    break;
                case 'images':
                    // First - show already attached images
                    $iCount = 0;
                    $input = '';
                    $tVars = ['images' => []];
                    //$tpl -> template('ed_entry.image', extras_dir.'/xfields/tpl');
                    if (is_array($SQLold['#images'])) {
                        foreach ($SQLold['#images'] as $irow) {
                            // Skip images, that are not related to current field
                            if (($irow['plugin'] != 'xfields') || ($irow['pidentity'] != $id)) {
                                continue;
                            }
                            // Show attached image
                            $iCount++;
                            $tImage = [
                                'number'      => $iCount,
                                'id'          => $id,
                                'preview'     => [
                                    'width'  => $irow['p_width'],
                                    'height' => $irow['p_height'],
                                    'url'    => $config['attach_url'].'/'.$irow['folder'].'/thumb/'.$irow['name'],
                                ],
                                'image'       => [
                                    'id'     => $irow['id'],
                                    'number' => $iCount,
                                    'url'    => $config['attach_url'].'/'.$irow['folder'].'/'.$irow['name'],
                                    'width'  => $irow['width'],
                                    'height' => $irow['height'],
                                ],
                                'flags'       => [
                                    'preview' => $irow['preview'] ? true : false,
                                    'exist'   => true,
                                ],
                                'description' => secure_html($irow['description']),
                            ];
                            $tVars['images'][] = $tImage;
                        }
                    }
                    // Second - show entries for allowed number of attaches
                    for ($i = $iCount + 1; $i <= intval($data['maxCount']); $i++) {
                        $tImage = [
                            'number' => $i,
                            'id'     => $id,
                            'flags'  => [
                                'exist' => false,
                            ],
                        ];
                        $tVars['images'][] = $tImage;
                    }
                    // Make template
                    $xt = $twig->loadTemplate('plugins/xfields/tpl/ed_entry.image.tpl');
                    $val = $xt->render($tVars);
                    $xfEntry['input'] = $val;
                    $xfEntries[intval($data['area'])][] = $xfEntry;
                    break;
            }
        }
        $xfCategories = [];
        foreach ($catz as $cId => $cData) {
            $xfCategories[$cData['id']] = $cData['xf_group'];
        }
        // Prepare table data [if needed]
        $flagTData = false;
        $tclist = [];
        $thlist = [];
        if (isset($xf['tdata']) && is_array($xf['tdata'])) {
            // Load table data for specific news
            $tlist = [];
            foreach ($mysql->select('select * from '.prefix.'_xfields where (linked_ds = 1) and (linked_id = '.db_squote($newsID).')') as $trow) {
                $ts = unserialize($trow['xfields']);
                $tEntry = ['#id' => $trow['id']];
                // Scan every field for value
                foreach ($xf['tdata'] as $fId => $fData) {
                    $fValue = '';
                    if (is_array($ts) && isset($ts[$fId])) {
                        $fValue = $ts[$fId];
                    } elseif (isset($trow['xfields_'.$fId])) {
                        $fValue = $trow['xfields_'.$fId];
                    }
                    $tEntry[$fId] = $fValue;
                }
                $tlist[] = $tEntry;
            }
            // Prepare config
            foreach ($xf['tdata'] as $fId => $fData) {
                if ($fData['disabled']) {
                    continue;
                }
                $flagTData = true;
                $tclist[$fId] = [
                    'title'    => $fData['title'],
                    'required' => $fData['required'],
                    'type'     => $fData['type'],
                    'default'  => $fData['default'],
                ];
                $thlist[] = [
                    'id'    => $fId,
                    'title' => $fData['title'],
                ];
                if ($fData['type'] == 'select') {
                    $tclist[$fId]['storekeys'] = $fData['storekeys'];
                    $tclist[$fId]['options'] = $fData['options'];
                }
            }
        }
        // Prepare personal [group] variables
        $xfNews = $xf['news'] ?: [];
        $tVars = [
            //	'entries'		=>	$xfEntries[0],
            'xfGC'       => json_encode($xf['grp.news']),
            'xfCat'      => json_encode($xfCategories),
            'xfList'     => json_encode(array_keys($xfNews)),
            'xtableConf' => json_encode($tclist),
            'xtableVal'  => json_encode($tlist),
            'xtableHdr'  => $thlist,
            'xtablecnt'  => count($thlist),
            'flags'      => [
                'tdata' => $flagTData,
            ],
        ];
        if (!isset($xfEntries[0])) {
            $xfEntries[0] = [];
        }
        foreach ($xfEntries as $k => $v) {
            // Check if we have template for specific area, elsewhere - use basic [0] template
            $templateName = 'plugins/xfields/tpl/news.edit.'.(file_exists(root.'plugins/xfields/tpl/news.edit.'.$k.'.tpl') ? $k : '0').'.tpl';
            $xt = $twig->loadTemplate($templateName);
            $tVars['entries'] = $v;
            $tVars['entryCount'] = count($v);
            $tVars['area'] = $k;
            // Table data is available only for area 0
            $tVars['flags']['tdata'] = (!$k) ? $flagTData : 0;
            // Render block
            $tvars['plugin']['xfields'][$k] .= $xt->render($tVars);
        }
        unset($tVars['entries']);
        unset($tVars['area']);
        // Render general part [with JavaScript]
        $xt = $twig->loadTemplate('plugins/xfields/tpl/news.general.tpl');
        $tvars['plugin']['xfields']['general'] = $xt->render($tVars);

        return 1;
    }

    public function editNews($newsID, $SQLold, &$SQLnew, &$tvars)
    {
        global $lang, $config, $mysql;
        //	print "<pre>POST VARS: ".var_export($_POST, true)."</pre>";
        // Load config
        $xf = xf_configLoad();
        if (!is_array($xf)) {
            return 1;
        }
        $rcall = $_POST['xfields'];
        if (!is_array($rcall)) {
            $rcall = [];
        }
        // Decode previusly stored data
        $oldFields = xf_decode($SQLold['xfields']);
        // Manage attached images
        xf_modifyAttachedImages(1, $newsID, $xf, $SQLold['#images']);
        $xdata = [];
        // Scan fields and check if we have attached images for fields with type 'images'
        $haveImages = false;
        foreach ($xf['news'] as $fid => $fval) {
            if ($fval['type'] == 'images') {
                $haveImages = true;
                break;
            }
        }
        if ($haveImages) {
            // Get real ID's of attached images and print here
            $idlist = [];
            foreach ($mysql->select('select id, plugin, pidentity from '.prefix.'_images where (linked_ds = 1) and (linked_id = '.db_squote($newsID).')') as $irec) {
                if ($irec['plugin'] == 'xfields') {
                    $idlist[$irec['pidentity']][] = $irec['id'];
                }
            }
            // Scan for fields that should be configured to have attached images
            foreach ($xf['news'] as $fid => $fval) {
                if (($fval['type'] == 'images') && (is_array($idlist[$fid]))) {
                    $xdata[$fid] = implode(',', $idlist[$fid]);
                }
            }
        }
        foreach ($xf['news'] as $id => $data) {
            // Attached images are processed in special way
            if ($data['type'] == 'images') {
                continue;
            }
            // Skip disabled fields
            if ($data['disabled']) {
                $xdata[$id] = $oldFields[$id];
                continue;
            }
            if ($rcall[$id] != '') {
                $xdata[$id] = $rcall[$id];
            } elseif ($data['required']) {
                msg(['type' => 'error', 'text' => $id, 'info' => str_replace('{field}', $id, $lang['xfields_msge_emptyrequired'])]);

                return 0;
            }
            // Check if we should save data into separate SQL field
            if ($data['storage']) {
				if($data['db.type'] == 'int'){
					$SQLnew['xfields_'.$id] = intval($rcall[$id]);
				}else{
					$SQLnew['xfields_'.$id] = $rcall[$id];
				}
            }
        }
        // Prepare table data [if needed]
        $haveTable = false;
        if (isset($xf['tdata']) && is_array($xf['tdata']) && isset($_POST['xftable']) && is_array($xft = json_decode($_POST['xftable'], true))) {

            //print "<pre>[".(is_array($xft)?'ARR':'NOARR')."]INCOMING ARRAY: ".var_export($xft, true)."</pre>";
            $recList = [];
            $queryList = [];
            // SCAN records
            foreach ($xft as $k => $v) {
                if (is_array($v) && isset($v['#id'])) {
                    $editMode = 0;
                    $tOldRec = [];
                    $tOldRecX = [];
                    if (intval($v['#id'])) {
                        $recList[] = intval($v['#id']);
                        $editMode = 1;
                        $tOldRec = $mysql->record('select * from '.prefix.'_xfields where (id = '.intval($v['#id']).') and (linked_ds = 1) and (linked_id = '.intval($newsID).')');
                        $tOldRecX = unserialize($tOldRec['xfields']);
                    }
                    $tRec = ['xfields' => []];
                    foreach ($xf['tdata'] as $fId => $fData) {
                        // Manage disabled fields
                        if ($fData['disabled']) {
                            $tRec['xfields'][$fId] = $tOldRecX[$fId];
                            continue;
                        }
                        if ($fData['storage']) {
                            $tRec['xfields_'.$fId] = db_squote($v[$fId]);
                        }
                        $tRec['xfields'][$fId] = $v[$fId];
                    }
                    $tRec['xfields'] = db_squote(serialize($tRec['xfields']));
                    // Now update record info
                    $haveTable = true;
                    if ($editMode) {
                        $vt = [];
                        foreach ($tRec as $kx => $vx) {
                            $vt[] = $kx.' = '.$vx;
                        }
                        $query = 'update '.prefix.'_xfields set '.implode(', ', $vt).' where (id = '.intval($v['#id']).') and (linked_ds = 1) and (linked_id = '.intval($newsID).')';
                        //print "SQL: $query <br/>\n";
                        $queryList[] = $query;
                    //$mysql->query($query);
                    } else {
                        $query = 'insert into '.prefix.'_xfields ('.implode(', ', array_keys($tRec)).', linked_ds, linked_id) values ('.implode(', ', array_values($tRec)).', 1, '.(intval($newsID)).')';
                        //print "SQL: $query <br/>\n";
                        $queryList[] = $query;
                        //$mysql->query($query);
                    }
                    //print "GOT LINE:<pre>".var_export($tRec, true)."</pre>";
                }
            }
            // Now delete old lines
            if (count($recList)) {
                $query = 'delete from '.prefix.'_xfields where (linked_ds = 1) and (linked_id = '.intval($newsID).') and id not in ('.implode(', ', $recList).')';
            } else {
                $query = 'delete from '.prefix.'_xfields where (linked_ds = 1) and (linked_id = '.intval($newsID).')';
            }
            $mysql->query($query);
            // Execute queries
            foreach ($queryList as $query) {
                $mysql->query($query);
            }
        }
        // Save info about table data
        if ($haveTable) {
            $xdata['#table'] = 1;
        }
        $SQLnew['xfields'] = xf_encode($xdata);

        return 1;
    }

    // Delete news notifier [ after news is deleted ]
    public function deleteNewsNotify($newsID, $SQLnews)
    {
        global $mysql;
        $query = 'delete from '.prefix.'_xfields where (linked_ds = 1) and (linked_id = '.intval($newsID).')';
        $mysql->query($query);

        return 1;
    }

    // Called before showing list of news
    public function onBeforeShowlist($callingParams)
    {
        if (isset($linkedFiles['data']) && is_array($linkedFiles['data'])) {
            // Check for news that have attached TABLE data and load this table into memory
            // ...
        }
    }

    // Show news call :: processor (call after all processing is finished and before show)
    public function showNews($newsID, $SQLnews, &$tvars, $mode = [])
    {
        global $mysql, $config, $twigLoader, $twig, $PFILTERS, $twig, $twigLoader, $parse, $kp;
        // Try to load config. Stop processing if config was not loaded
        if (($xf = xf_configLoad()) === false) {
            return;
        }
        $fields = xf_decode($SQLnews['xfields']);
        $content = $SQLnews['content'];
        // Check if we have at least one `image` field and load TWIG template if any
        if (is_array($xf['news'])) {
            foreach ($xf['news'] as $k => $v) {
                if ($v['type'] == 'images') {
                    // Yes, we have it!
                    $conversionParams = [];
                    $imagesTemplateFileName = 'plugins/xfields/tpl/news.show.images.tpl';
                    $twigLoader->setConversion($imagesTemplateFileName, $conversionConfig);
                    $xtImages = $twig->loadTemplate($imagesTemplateFileName);
                    break;
                }
            }
        }
        // Show extra fields if we have it
        if (is_array($xf['news'])) {
            foreach ($xf['news'] as $k => $v) {
                $kp = preg_quote($k, '#');
                $xfk = isset($fields[$k]) ? $fields[$k] : '';
                // TWIG stype data fill
                $tvars['vars']['p']['xfields'][$k]['type'] = $v['type'];
                $tvars['vars']['p']['xfields'][$k]['title'] = secure_html($v['title']);
                // Our behaviour depends on field type
                if ($v['type'] == 'images') {
                    // Check if there're attached images
                    $imglist = [];
                    if ($xfk && count($ilist = explode(',', $xfk))) {
                        // Check if we have already preloaded (by engine) images
                        $ilk = [];
                        foreach ($ilist as $irec) {
                            if (isset($mode['linkedImages']['data'][$irec])) {
                                $imglist[] = $mode['linkedImages']['data'][$irec];
                            } else {
                                $ilk[] = $irec;
                            }
                        }
                        // Check if we have some not loaded news
                        if (count($ilk) && count($timglist = $mysql->select('select * from '.prefix.'_images where id in ('.$xfk.')'))) {
                            $imglist = array_merge($imglist, $timglist);
                            unset($timglist);
                        }
                    }
					$imgInfo = preg_replace_callback ( "#<(img|iframe)(.+?)>#i", "lazyload", $imglist );
                    //					if ($xfk && count($ilist = explode(",", $xfk)) && count($imglist = $mysql->select("select * from ".prefix."_images where id in (".$xfk.")"))) {
                    if (count($imglist)) {
                        // Yes, show field block
                        $tvars['regx']["#\[xfield_".$kp."\](.*?)\[/xfield_".$kp."\]#is"] = '$1';
                        $tvars['regx']["#\[nxfield_".$kp."\](.*?)\[/nxfield_".$kp."\]#is"] = '';
                        // Scan for images and prepare data for template show
                        $tiVars = [
                            'fieldName'    => $k,
                            'fieldTitle'   => secure_html($v['title']),
                            'fieldType'    => $v['type'],
                            'entriesCount' => count($imglist),
                            'entries'      => [],
                            'execStyle'    => $mode['style'],
                            'execPlugin'   => $mode['plugin'],
                        ];
                        foreach ($imglist as $imgInfo) {
                            $tiEntry = [
                                'url'         => ($imgInfo['storage'] ? $config['attach_url'] : $config['images_url']).'/'.$imgInfo['folder'].'/'.$imgInfo['name'],
                                'width'       => $imgInfo['width'],
                                'height'      => $imgInfo['height'],
                                'pwidth'      => $imgInfo['p_width'],
                                'pheight'     => $imgInfo['p_height'],
                                'name'        => $imgInfo['name'],
                                'origName'    => secure_html($imgInfo['orig_name']),
                                'description' => secure_html($imgInfo['description']),
                                'flags'       => [
                                    'hasPreview' => $imgInfo['preview'],
                                ],
                            ];
                            if ($imgInfo['preview']) {
                                $tiEntry['purl'] = ($imgInfo['storage'] ? $config['attach_url'] : $config['images_url']).'/'.$imgInfo['folder'].'/thumb/'.$imgInfo['name'];
                            }
                            $tiVars['entries'][] = $tiEntry;
                        }
                        // TWIG based variables
                        $tvars['vars']['p']['xfields'][$k]['entries'] = $tiVars['entries'];
                        $tvars['vars']['p']['xfields'][$k]['count'] = count($tiVars['entries']);
                        $xv = $xtImages->render($tiVars);
                        $tvars['vars']['p']['xfields'][$k]['value'] = $xv;
                        $tvars['vars']['[xvalue_'.$k.']'] = $xv;
                    } else {
                        // TWIG based variables
                        $tvars['vars']['p']['xfields'][$k]['value'] = '';
                        $tvars['vars']['p']['xfields'][$k]['count'] = 0;
                        $tvars['vars']['p']['xfields'][$k]['entries'] = [];
                        // General variables
                        $tvars['regx']["#\[xfield_".$kp."\](.*?)\[/xfield_".$kp."\]#is"] = '';
                        $tvars['regx']["#\[nxfield_".$kp."\](.*?)\[/nxfield_".$kp."\]#is"] = '$1';
                        $tvars['vars']['[xvalue_'.$k.']'] = '';
                    }
                } else {
                    $tvars['regx']["#\[xfield_".$kp."\](.*?)\[/xfield_".$kp."\]#is"] = ($xfk == '') ? '' : '$1';
                    $tvars['regx']["#\[nxfield_".$kp."\](.*?)\[/nxfield_".$kp."\]#is"] = ($xfk == '') ? '$1' : '';
                    // Process `HTML` support feature
                    if ((!$v['html_support']) && (($v['type'] == 'textarea') || ($v['type'] == 'text'))) {
                        $xfk = str_replace('<', '&lt;', $xfk);
                    }
                    // Parse BB code [if required]
                    if ($config['use_bbcodes'] && $v['bb_support']) {
                        $xfk = $parse->bbcodes($xfk);
                    }
                    // гиперссылка гк
                    if (($v['type'] == 'text') && ($v['xf_field']) && ($v['storage'] == 1)) {
						
						$temp_array = explode(',', $xfk);
						foreach ($temp_array as $xf_id) {
							$xf_id = trim($xf_id);
							$xf_t = (count($temp_array) > 1) ? ', ' : '';
							if (!$xf_id) continue;
							$xf_link = checkLinkAvailable('xfields', '') ?
								generateLink('xfields', '', array('xf_id' => $xf_id)) :
								generateLink('core', 'plugin', array('plugin' => 'xfields'), array('xf_id' => $xf_id));
								
							$xk_link .= '<a title="'.$v['title'].': '.$xf_id.'" href="'.$xf_link.'" target="_blank">'.$xf_id.$v['id'].'</a>'.$xf_t;
						}

						$xfk = $xk_link;
						
						unset($temp_array);
						unset($xk_link);
                          
                    }
                    // Process formatting
                    if (($v['type'] == 'textarea') && (!$v['noformat'])) {
                        $xfk = (str_replace("\n", "<br/>\n", $xfk).(strlen($xfk) ? '<br/>' : ''));
                    }
                    $tvars['vars']['p']['xfields'][$k]['value'] = $xfk;
                    $tvars['vars']['[xvalue_'.$k.']'] = $xfk;
                }
            }
        }
        // Show table if we have it
        if (isset($xf['tdata']) && is_array($xf['tdata']) && isset($fields['#table']) && ($fields['#table'] == 1)) {
            // Yes, we have table. Display it!
            // Prepare conversion table
            $conversionConfig = [
                '[entries]'  => '{% for entry in entries %}',
                '[/entries]' => '{% endfor %}',
            ];
            $xrecs = [];
            $npp = 1;
            foreach ($mysql->select('select * from '.prefix.'_xfields where (linked_ds = 1) and (linked_id = '.db_squote($newsID).') order by id', 1) as $trec) {
                $xrec = [
                    'num'   => ($npp++),
                    'id'    => $trec['id'],
                    'flags' => [],
                ];
                foreach ($xf['tdata'] as $tid => $tval) {
                    // Skip disabled
                    if ($tval['disabled']) {
                        continue;
                    }
                    //  Populate field data
                    $drec = unserialize($trec['xfields']);
                    $xrec['field_'.$tid] = $drec[$tid];
                    $xrec['flags']['field_'.$tid] = ($drec[$tid] != '') ? 1 : 0;
                    $conversionConfig['{entry_field_'.$tid.'}'] = '{{ entry.field_'.$tid.' }}';
                }
                // Process filters (if any)
                if (isset($PFILTERS['xfields']) && is_array($PFILTERS['xfields'])) {
                    foreach ($PFILTERS['xfields'] as $k => $v) {
                        $v->showTableEntry($newsID, $SQLnews, $trec, $xrec);
                    }
                }
                $xrecs[] = $xrec;
            }
            // Search for news.table.tpl template file
            $tpath = locatePluginTemplates(['news.table'], 'xfields');
            // Show table
            $templateName = $tpath['news.table'].'news.table.tpl';
            $twigLoader->setConversion($templateName, $conversionConfig);
            $xt = $twig->loadTemplate($templateName);
            $tvars['vars']['plugin_xfields_table'] = $xt->render(['entries' => $xrecs]);
            $tvars['vars']['p']['xfields']['_table']['countRec'] = count($xrecs);
            $tvars['vars']['p']['xfields']['_table']['data'] = $xrecs;
        } else {
            $tvars['vars']['plugin_xfields_table'] = '';
            $tvars['vars']['p']['xfields']['_table']['countRec'] = 0;
        }
        $SQLnews['content'] = $content;
    }
}

// Manage uprofile modifications
if (getPluginStatusActive('uprofile')) {
    loadPluginLibrary('uprofile', 'lib');

    class XFieldsUPrifileFilter extends p_uprofileFilter
    {
        public function editProfileForm($userID, $SQLrow, &$tvars)
        {
            global $lang, $catz, $mysql, $config, $twig, $twigLoader;
            //print "<pre>".var_export($lang, true)."</pre>";
            // Load config
            $xf = xf_configLoad();
            if (!is_array($xf)) {
                return false;
            }
            // Fetch xfields data
            $xdata = xf_decode($SQLrow['xfields']);
            if (!is_array($xdata)) {
                return false;
            }
            $output = '';
            $xfEntries = [];
            $xfList = [];
            foreach ($xf['users'] as $id => $data) {
                if ($data['disabled']) {
                    continue;
                }
                //print "FLD: [$id]<br>\n";
                $xfEntry = [
                    'title'        => $data['title'],
                    'id'           => $id,
                    'value'        => $xdata[$id],
                    'secure_value' => secure_html($xdata[$id]),
                    'data'         => $data,
                    'required'     => $lang['xfields_fld_'.($data['required'] ? 'required' : 'optional')],
                    'flags'        => [
                        'required' => $data['required'] ? true : false,
                    ],
                ];
                switch ($data['type']) {
                    case 'checkbox':
                        $val = '<input type="checkbox" id="form_xfields_'.$id.'" name="xfields['.$id.']" title="'.$data['title'].'" value="1" '.($data['default'] ? 'checked="checked"' : '').'"/>';
                        $xfEntry['input'] = $val;
                        break;
                    case 'text':
                        $val = '<input type="text" name="xfields['.$id.']"  id="form_xfields_'.$id.'" title="'.$data['title'].'" value="'.secure_html($xdata[$id]).'" />';
                        $xfEntry['input'] = $val;
                        break;
                    case 'select':
                        $val = '<select name="xfields['.$id.']" id="form_xfields_'.$id.'" >';
                        if (!$data['required']) {
                            $val .= '<option value="">&nbsp;</option>';
                        }
                        if (is_array($data['options'])) {
                            foreach ($data['options'] as $k => $v) {
                                $val .= '<option value="'.secure_html(($data['storekeys']) ? $k : $v).'"'.((($data['storekeys'] && ($xdata[$id] == $k)) || (!$data['storekeys'] && ($xdata[$id] == $v))) ? ' selected' : '').'>'.$v.'</option>';
                            }
                        }
                        $val .= '</select>';
                        $xfEntry['input'] = $val;
                        break;
                    case 'textarea':
                        $val = '<textarea cols="30" rows="4" name="xfields['.$id.']" id="form_xfields_'.$id.'">'.$xdata[$id].'</textarea>';
                        $xfEntry['input'] = $val;
                        break;
                    case 'images':
                        // First - show already attached images
                        $iCount = 0;
                        $input = '';
                        $tVars = ['images' => []];
                        if (is_array($SQLrow['#images'])) {
                            foreach ($SQLrow['#images'] as $irow) {
                                // Skip images, that are not related to current field
                                if (($irow['plugin'] != 'xfields') || ($irow['pidentity'] != $id)) {
                                    continue;
                                }
                                // Show attached image
                                $iCount++;
                                $tImage = [
                                    'number'  => $iCount,
                                    'id'      => $id,
                                    'preview' => [
                                        'width'  => $irow['p_width'],
                                        'height' => $irow['p_height'],
                                        'url'    => $config['attach_url'].'/'.$irow['folder'].'/thumb/'.$irow['name'],
                                    ],
                                    'image'   => [
                                        'id'     => $irow['id'],
                                        'number' => $iCount,
                                        'url'    => $config['attach_url'].'/'.$irow['folder'].'/'.$irow['name'],
                                        'width'  => $irow['width'],
                                        'height' => $irow['height'],
                                    ],
                                    'flags'   => [
                                        'preview' => $irow['preview'] ? true : false,
                                        'exist'   => true,
                                    ],
                                ];
                                $tVars['images'][] = $tImage;
                            }
                        }
                        // Second - show entries for allowed number of attaches
                        for ($i = $iCount + 1; $i <= intval($data['maxCount']); $i++) {
                            $tImage = [
                                'number' => $i,
                                'id'     => $id,
                                'flags'  => [
                                    'exist' => false,
                                ],
                            ];
                            $tVars['images'][] = $tImage;
                        }
                        // Make template
                        $xt = $twig->loadTemplate('plugins/xfields/tpl/ed_entry.image.tpl');
                        $val = $xt->render($tVars);
                        $xfEntry['input'] = $val;
                        break;
                    default:
                        continue(2);
                }
                $xfEntries[intval($data['area'])][] = $xfEntry;
                $xfList[$id] = $xfEntry;
            }
            // Prepare configuration array
            $tVars = [];
            // Area 0 should always be configured
            if (!isset($xfEntries[0])) {
                $xfEntries[0] = [];
            }
            // For compatibility with old template engine, init values for blocks 0 and 1
            $tvars['plugin_xfields_0'] = '';
            $tvars['plugin_xfields_1'] = '';
            foreach ($xfEntries as $k => $v) {
                // Check if we have template for specific area, elsewhere - use basic [0] template
                $templateName = 'plugins/xfields/tpl/uprofile.edit.'.(file_exists(root.'plugins/xfields/tpl/uprofile.edit.'.$k.'.tpl') ? $k : '0').'.tpl';
                $xt = $twig->loadTemplate($templateName);
                $tVars['entries'] = $v;
                $tVars['entryCount'] = count($v);
                $tVars['area'] = $k;
                // Render block
                $render = $xt->render($tVars);
                $tvars['plugin_xfields_'.$k] .= $render;
                $tvars['p']['xfields'][$k] .= $render;
            }
            $tvars['p']['xfields']['fields'] = $xfList;

            /*
                        unset($tVars['entries']);
                        unset($tVars['area']);

                        // Render general part [with JavaScript]
                        $xt = $twig->loadTemplate('plugins/xfields/tpl/news.general.tpl');
                        $tvars['p']['xfields']['general'] = $xt->render($tVars);


                        $xt = $twig->loadTemplate('plugins/xfields/tpl/ed_uprofile.tpl');
                        $tvars['vars']['plugin_xfields'] .= $xt->render($tVars);
            */

            return 1;
        }

        public function editProfile($userID, $SQLrow, &$SQLnew)
        {
            global $lang, $config, $mysql, $DSlist;
            //print "<pre>editProfile() POST VARS: ".var_export($_POST, true)."</pre>";
            // Load config
            $xf = xf_configLoad();
            if (!is_array($xf)) {
                return 1;
            }
            $rcall = $_POST['xfields'];
            if (!is_array($rcall)) {
                $rcall = [];
            }
            // Decode previusly stored data
            $oldFields = xf_decode($SQLrow['xfields']);
            // Manage attached images
            xf_modifyAttachedImages($DSlist['users'], $userID, $xf, $SQLrow['#images']);
            $xdata = [];
            //print "XF[users]: <pre>".var_export($xf['users'], true)."</pre>";
            // Scan fields and check if we have attached images for fields with type 'images'
            $haveImages = false;
            foreach ($xf['users'] as $fid => $fval) {
                if ($fval['type'] == 'images') {
                    $haveImages = true;
                    break;
                }
            }
            if ($haveImages) {
                // Get real ID's of attached images and print here
                $idlist = [];
                foreach ($mysql->select('select id, plugin, pidentity from '.prefix.'_images where (linked_ds = '.$DSlist['users'].') and (linked_id = '.db_squote($userID).')') as $irec) {
                    if ($irec['plugin'] == 'xfields') {
                        $idlist[$irec['pidentity']][] = $irec['id'];
                    }
                }
                // Scan for fields that should be configured to have attached images
                foreach ($xf['users'] as $fid => $fval) {
                    if (($fval['type'] == 'images') && (is_array($idlist[$fid]))) {
                        $xdata[$fid] = implode(',', $idlist[$fid]);
                    }
                }
            }
            foreach ($xf['users'] as $id => $data) {
                // Attached images are processed in special way
                if ($data['type'] == 'images') {
                    continue;
                }
                // Skip disabled fields
                if ($data['disabled']) {
                    $xdata[$id] = $SQLrow[$id];
                    continue;
                }
                if ($rcall[$id] != '') {
                    $xdata[$id] = $rcall[$id];
                } elseif ($data['required']) {
                    msg(['type' => 'error', 'text' => $id, 'info' => str_replace('{field}', $id, $lang['xfields_msge_emptyrequired'])]);

                    return 0;
                }
                // Check if we should save data into separate SQL field
                if ($data['storage']) {
					if($data['db.type'] == 'int'){
						$SQLnew['xfields_'.$id] = intval($rcall[$id]);
					}else{
						$SQLnew['xfields_'.$id] = $rcall[$id];
					}
                }
            }
            $SQLnew['xfields'] = xf_encode($xdata);

            return 1;
        }

        public function showProfile($userID, $SQLrow, &$tvars)
        {
            global $mysql, $config, $twig, $twigLoader, $parse;
            // Try to load config. Stop processing if config was not loaded
            if (($xf = xf_configLoad()) === false) {
                return;
            }
            $fields = xf_decode($SQLrow['xfields']);
            // Check if we have at least one `image` field and load TWIG template if any
            if (is_array($xf['users'])) {
                foreach ($xf['users'] as $k => $v) {
                    if ($v['type'] == 'images') {
                        // Yes, we have it!
                        $conversionParams = [];
                        $imagesTemplateFileName = 'plugins/xfields/tpl/profile.show.images.tpl';
                        $twigLoader->setConversion($imagesTemplateFileName, $conversionConfig);
                        $xtImages = $twig->loadTemplate($imagesTemplateFileName);
                        break;
                    }
                }
            }
            // Show extra fields if we have it
            if (is_array($xf['users'])) {
                foreach ($xf['users'] as $k => $v) {
                    $kp = preg_quote($k, '#');
                    $xfk = isset($fields[$k]) ? $fields[$k] : '';
                    // Our behaviour depends on field type
                    if ($v['type'] == 'images') {
                        // Check if there're attached images
                        if ($xfk && count($ilist = explode(',', $xfk)) && count($imglist = $mysql->select('select * from '.prefix.'_images where id in ('.$xfk.')'))) {
                            //print "-xGotIMG[$k]";
                            // Yes, get list of images
                            $imgInfo = $imglist[0];
                            $tvars['regx']["#\[xfield_".$kp."\](.*?)\[/xfield_".$kp."\]#is"] = '$1';
                            $tvars['regx']["#\[nxfield_".$kp."\](.*?)\[/nxfield_".$kp."\]#is"] = '';
                            $iname = ($imgInfo['storage'] ? $config['attach_url'] : $config['files_url']).'/'.$imgInfo['folder'].'/'.$imgInfo['name'];
                            $tvars['vars']['[xvalue_'.$k.']'] = $iname;
                            // Scan for images and prepare data for template show
                            $tiVars = [
                                'fieldName'    => $k,
                                'fieldTitle'   => secure_html($v['title']),
                                'fieldType'    => $v['type'],
                                'entriesCount' => count($imglist),
                                'entries'      => [],
                                'execStyle'    => $mode['style'],
                                'execPlugin'   => $mode['plugin'],
                            ];
                            foreach ($imglist as $imgInfo) {
                                $tiEntry = [
                                    'url'         => ($imgInfo['storage'] ? $config['attach_url'] : $config['images_url']).'/'.$imgInfo['folder'].'/'.$imgInfo['name'],
                                    'width'       => $imgInfo['width'],
                                    'height'      => $imgInfo['height'],
                                    'pwidth'      => $imgInfo['p_width'],
                                    'pheight'     => $imgInfo['p_height'],
                                    'name'        => $imgInfo['name'],
                                    'origName'    => secure_html($imgInfo['orig_name']),
                                    'description' => secure_html($imgInfo['description']),
                                    'flags'       => [
                                        'hasPreview' => $imgInfo['preview'],
                                    ],
                                ];
                                if ($imgInfo['preview']) {
                                    $tiEntry['purl'] = ($imgInfo['storage'] ? $config['attach_url'] : $config['images_url']).'/'.$imgInfo['folder'].'/thumb/'.$imgInfo['name'];
                                }
                                $tiVars['entries'][] = $tiEntry;
                            }
                            // TWIG based variables
                            $tvars['p']['xfields'][$k]['entries'] = $tiVars['entries'];
                            $tvars['p']['xfields'][$k]['count'] = count($tiVars['entries']);
                            $xv = $xtImages->render($tiVars);
                            $tvars['p']['xfields'][$k]['value'] = $xv;
                        //$tvars['vars']['[xvalue_'.$k.']'] = $xv;
                        } else {
                            $tvars['regx']["#\[xfield_".$kp."\](.*?)\[/xfield_".$kp."\]#is"] = '';
                            $tvars['regx']["#\[nxfield_".$kp."\](.*?)\[/nxfield_".$kp."\]#is"] = '$1';
                        }
                    } else {
                        $tvars['regx']["#\[xfield_".$kp."\](.*?)\[/xfield_".$kp."\]#is"] = ($xfk == '') ? '' : '$1';
                        $tvars['regx']["#\[nxfield_".$kp."\](.*?)\[/nxfield_".$kp."\]#is"] = ($xfk == '') ? '$1' : '';
                        $tvars['vars']['[xvalue_'.$k.']'] = ($v['type'] == 'textarea') ? '<br/>'.(str_replace("\n", "<br/>\n", $xfk).(strlen($xfk) ? '<br/>' : '')) : $xfk;
                        // 12345
                        // Process `HTML` support feature
                        if ((!$v['html_support']) && (($v['type'] == 'textarea') || ($v['type'] == 'text'))) {
                            $xfk = str_replace('<', '&lt;', $xfk);
                        }
                        // Parse BB code [if required]
                        if ($config['use_bbcodes'] && $v['bb_support']) {
                            $xfk = $parse->bbcodes($xfk);
                        }
                        // Process formatting
                        if (($v['type'] == 'textarea') && (!$v['noformat'])) {
                            $xfk = (str_replace("\n", "<br/>\n", $xfk).(strlen($xfk) ? '<br/>' : ''));
                        }
                        // TWIG based variables
                        $tvars['p']['xfields'][$k]['value'] = $xfk;
                    }
                }
            }
        }
    }

    register_filter('plugin.uprofile', 'xfields', new XFieldsUPrifileFilter());
}

class XFieldsFilterAdminCategories extends FilterAdminCategories
{
    public function addCategory(&$tvars, &$SQL)
    {
        $SQL['xf_group'] = $_REQUEST['xf_group'];

        return 1;
    }

    public function addCategoryForm(&$tvars)
    {
        global $lang;
        loadPluginLang('xfields', 'config', '', '', ':');
        // Get config
        $xf = xf_configLoad();
        // Prepare select
        $ms = '<select name="xf_group"><option value="">** все поля **</option>';
        if (isset($xf['grp.news'])) {
            foreach ($xf['grp.news'] as $k => $v) {
                $ms .= '<option value="'.$k.'">'.$k.' ('.$v['title'].')</option>';
            }
        }
        $tvars['extend'] .= '<tr><td width="70%" class="contentEntry1">'.$lang['xfields:categories.group'].'<br/><small>'.$lang['xfields:categories.group#desc'].'</small></td><td width="30%" class="contentEntry2">'.$ms.'</td></tr>';

        return 1;
    }

    public function editCategoryForm($categoryID, $SQL, &$tvars)
    {
        global $lang;
        loadPluginLang('xfields', 'config', '', '', ':');
        // Get config
        $xf = xf_configLoad();
        // Prepare select
        $ms = '<select name="xf_group"><option value="">** все поля **</option>';
        foreach ($xf['grp.news'] as $k => $v) {
            $ms .= '<option value="'.$k.'"'.(($SQL['xf_group'] == $k) ? ' selected="selected"' : '').'>'.$k.' ('.$v['title'].')</option>';
        }
        $tvars['extend'] .= '<tr><td width="70%" class="contentEntry1">'.$lang['xfields:categories.group'].'<br/><small>'.$lang['xfields:categories.group#desc'].'</small></td><td width="30%" class="contentEntry2">'.$ms.'</td></tr>';

        return 1;
    }

    public function editCategory($categoryID, $SQL, &$SQLnew, &$tvars)
    {
        $SQLnew['xf_group'] = $_REQUEST['xf_group'];

        return 1;
    }
}

class XFieldsCoreFilter extends CoreFilter
{
    public function registerUserForm(&$tvars)
    {

        // Load config
        $xf = xf_configLoad();
        if (!is_array($xf) || !isset($xf['users']) || !is_array($xf['users'])) {
            return 1;
        }
        foreach ($xf['users'] as $k => $v) {
            if ($v['regpage'] && !$v['disabled']) {
                //print "$k: <pre>".var_export($v, true)."</pre>";
                $tEntry = [
                    'name'  => 'xfield_'.$k,
                    'title' => $v['title'],
                ];
                switch ($v['type']) {
                    case 'text':
                        $tEntry['type'] = 'input';
                        $tEntry['value'] = $v['default'];
                        break;
                    case 'textarea':
                        $tEntry['type'] = 'text';
                        $tEntry['value'] = $v['default'];
                        break;
                    case 'select':
                        $tEntry['type'] = 'select';
                        if ($v['required']) {
                            $tEntry['values'] = $v['options'];
                        } else {
                            $tEntry['values'] = ['' => ''] + $v['options'];
                        }
                        $tEntry['value'] = $v['default'];
                        break;
                }
                $tvars['entries'][] = $tEntry;
            }
        }

        return 1;
    }

    public function registerUserNotify($userID, $userRec)
    {
        global $mysql;
        // Load config
        $xf = xf_configLoad();
        if (!is_array($xf) || !isset($xf['users']) || !is_array($xf['users'])) {
            return 1;
        }
        $xdata = [];
        $SQL = [];
        foreach ($xf['users'] as $k => $v) {
            if ($v['regpage'] && !$v['disabled']) {
                switch ($v['type']) {
                    case 'text':
                    case 'textarea':
                    case 'select':
                        $xdata[$k] = $_POST['xfield_'.$k];
                        if ($v['storage']) {
                            $SQL['xfields_'.$k] = $xdata[$k];
                        }
                        break;
                }
            }
        }
        $SQL['xfields'] = xf_encode($xdata);
        $SQ = [];
        foreach ($SQL as $sk => $sv) {
            $SQ[] = $sk.'='.db_squote($sv);
        }
        $mysql->query('update '.uprefix.'_users set '.implode(',', $SQ).' where id = '.intval($userID));

        return 1;
    }
}

function xfields_link($params) {
	global $tpl, $template, $twig, $mysql, $SYSTEM_FLAGS, $config, $userROW, $newsID, $CurrentHandler, $lang, $parse, $TemplateCache;
	
	$tpath = locatePluginTemplates(array('show_xf', 'pages'), 'xfields', pluginGetVariable('xfields', 'localsource'), pluginGetVariable('xfields', 'skin') ? pluginGetVariable('xfields', 'skin') : 'default');
	include_once root . 'includes/news.php';
	
	LoadPluginLang('xfields', 'main', '', '', ':');
	
	$xf_id = isset($params['xf_id'])?$params['xf_id']:$CurrentHandler['params']['xf_id'];

	$pageNo	= intval($params['page']) ? intval($params['page']) : intval($_REQUEST['page']);
	
	$xf = xf_configLoad();

	$where = array();
	$temp_array = array();

	foreach ($xf['news'] as $k => $v) {
		$kp = preg_quote($k, "'");
		
		if ($v['xf_field']) {

			$match_xf = explode (',', $xf_id);
			foreach ($match_xf as $value) {
				$value = htmlspecialchars ( strip_tags ( stripslashes ( trim ( $value ) ) ), ENT_QUOTES, "UTF-8" ) ;
				$temp_array[] = "xfields_".$kp." LIKE '%{$value}%'";
			}

		}
	}	

	if ($pageNo){
		$meta_group = ''.$xf_id.' '.$config['separator'].' '.$kp.' страница - '.$pageNo.'';
	} else {
		$meta_group = ''.$xf_id.' '.$config['separator'].' '.$kp.'';			
	}
	
	$SYSTEM_FLAGS['meta']['robots'] = 'noindex,nofollow';	
	$SYSTEM_FLAGS['info']['title']['header'] = 'Сортировка по полю';
	$SYSTEM_FLAGS['info']['title']['group'] = secure_html($meta_group);
	$SYSTEM_FLAGS['info']['title']['item'] = $config['home_title'];
	$SYSTEM_FLAGS['info']['breadcrumbs'] = array(
		array('text' => $xf_id),
	);
	
	$where[] = "(".implode(' OR ', $temp_array).")";
	$where[] = "approve=1";

	$limitCount = '8';
	if (($limitCount < 1) || ($limitCount > 1000))
		$limitCount = 1000;

	$count = $mysql->result('SELECT COUNT(*) as count FROM '.prefix.'_news WHERE '.implode(' AND ', $where).' ');
			
    if(!$count)
        return error404();
	
	$pagesCount = ceil($count / $limitCount);

	if ($pageNo < 1) $pageNo = 1;			
	if (!$limitStart)	$limitStart = ($pageNo - 1)* $limitCount;
	
	templateLoadVariables(true);

	$navigations = $TemplateCache['site']['#variables']['navigation'];
	
	$paginationParams = checkLinkAvailable('xfields', '') ?
		array('pluginName' => 'xfields', 'pluginHandler' => '', 'params' => array('xf_id' => $xf_id),  'xparams' => array(), 'paginator' => array('page', 0, false)) :
		array('pluginName' => 'core', 'pluginHandler' => 'plugin', 'params' => array('plugin' => 'xfields', 'handler' => ''), 'xparams' => array(), 'paginator' => array('page', 1, false));
				
	$tvars = array();
	$tvars['regx']["'\[prev-link\](.*?)\[/prev-link\]'si"] = ($pageNo > 1) ? str_replace('%page%', "$1", str_replace('%link%', generatePageLink($paginationParams, $pageNo - 1), $navigations['prevlink'])) : '';
	$tvars['regx']["'\[next-link\](.*?)\[/next-link\]'si"] = ($pageNo < $pagesCount) ? str_replace('%page%', "$1", str_replace('%link%', generatePageLink($paginationParams, $pageNo + 1), $navigations['nextlink'])) : '';
	$tvars['vars']['pages'] = generatePagination($pageNo, 1, $pagesCount, 10, $paginationParams, $navigations);
	$tpl->template('pages', $tpath['pages']);
	$tpl->vars('pages', $tvars);
	$pages = $tpl->show('pages');

	foreach ($mysql->select('select * from '.prefix.'_news WHERE '.implode(' AND ', $where).' ORDER BY id ASC LIMIT '.intval($limitStart).', '.intval($limitCount)) as $row){
		$entries .= news_showone($newsID, '', array('overrideTemplateName' => 'show_xf', 'overrideTemplatePath' => $tpath['show_xf'], 'emulate' => $row, 'style' => 'export', 'plugin' => 'xfields'));
	}

	$template['vars']['mainblock'] = $entries.$pages;

}

register_plugin_page('xfields','','xfields_link');
register_filter('news', 'xfields', new XFieldsNewsFilter());
register_filter('core.registerUser', 'xfields', new XFieldsCoreFilter());
register_admin_filter('categories', 'xfields', new XFieldsFilterAdminCategories());
