<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
* LimeSurvey
* Copyright (C) 2007-2012 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

function loadanswers()
{
    Yii::trace('start', 'survey.loadanswers');
    global $surveyid;
    global $thissurvey, $thisstep;
    global $clienttoken;


    $scid=Yii::app()->request->getQuery('scid');
    if (Yii::app()->request->getParam('loadall') == "reload")
    {
        $sLoadName=Yii::app()->request->getParam('loadname');
        $sLoadPass=Yii::app()->request->getParam('loadpass');
        $oCriteria= new CDbCriteria;
        $oCriteria->join="LEFT JOIN {{saved_control}} ON t.id={{saved_control}}.srid";
        $oCriteria->condition="{{saved_control}}.sid=:sid";
        $aParams=array(':sid'=>$surveyid);
        if (isset($scid)) //Would only come from email : we don't need it ....
        {
            $oCriteria->addCondition("{{saved_control}}.scid=:scid");
            $aParams[':scid']=$scid;
        }
        $oCriteria->addCondition("{{saved_control}}.identifier=:identifier");
        $aParams[':identifier']=$sLoadName;

        if (in_array(Yii::app()->db->getDriverName(), array('mssql', 'sqlsrv', 'dblib')))
        {
            // To be validated with mssql, think it's not needed
            $oCriteria->addCondition("(CAST({{saved_control}}.access_code as varchar(64))=:md5_code OR CAST({{saved_control}}.access_code as varchar(64))=:sha256_code)");
        }
        else
        {
            $oCriteria->addCondition("({{saved_control}}.access_code=:md5_code OR {{saved_control}}.access_code=:sha256_code)");
        }
        $aParams[':md5_code']=md5($sLoadPass);
        $aParams[':sha256_code']=hash('sha256',$sLoadPass);
    }
    elseif (isset($_SESSION['survey_'.$surveyid]['srid']))
    {
        $oCriteria= new CDbCriteria;
        $oCriteria->condition="id=:id";
        $aParams=array(':id'=>$_SESSION['survey_'.$surveyid]['srid']);
    }
    else
    {
        return;
    }
    $oCriteria->params=$aParams;
    $oResponses=SurveyDynamic::model($surveyid)->find($oCriteria);
    if (!$oResponses)
    {
        return false;
    }
    else
    {
        //A match has been found. Let's load the values!
        //If this is from an email, build surveysession first
        $_SESSION['survey_'.$surveyid]['LEMtokenResume']=true;

        // If survey come from reload (GET or POST); some value need to be found on saved_control, not on survey
        if (Yii::app()->request->getParam('loadall') == "reload")
        {
            $oSavedSurvey=SavedControl::model()->find("identifier=:identifier AND (access_code=:access_code OR access_code=:sha256_code)",array(':identifier'=>$sLoadName,':access_code'=>md5($sLoadPass),':sha256_code'=>hash('sha256',$sLoadPass)));
            // We don't need to control if we have one, because we do the test before
            $_SESSION['survey_'.$surveyid]['scid'] = $oSavedSurvey->scid;
            App()->surveySessionManager->current->setStep(($oSavedSurvey->saved_thisstep>1)?$oSavedSurvey->saved_thisstep:1);
            $thisstep = App()->surveySessionManager->current->getStep() - 1;// deprecated ?
            $_SESSION['survey_'.$surveyid]['srid'] = $oSavedSurvey->srid;// Seems OK without
            $_SESSION['survey_'.$surveyid]['refurl'] = $oSavedSurvey->refurl;
        }

        // Get if survey is been answered
        $submitdate=$oResponses->submitdate;
        $aRow=$oResponses->attributes;
        foreach ($aRow as $column => $value)
        {
            if ($column == "token")
            {
                $clienttoken=$value;
                $token=$value;
            }
            elseif ($column =='lastpage' && !isset($_SESSION['survey_'.$surveyid]['step']))
            {
                if(is_null($submitdate) || $submitdate=="N")
                {
                    App()->surveySessionManager->current->setStep($value > 1 ? $value : 1);
                    $thisstep = App()->surveySessionManager->current->getStep()-1;
                }
                else
                {
                    $_SESSION['survey_'.$surveyid]['maxstep']=($value>1? $value:1) ;
                }
            }
            elseif ($column == "datestamp")
            {
                $_SESSION['survey_'.$surveyid]['datestamp']=$value;
            }
            if ($column == "startdate")
            {
                $_SESSION['survey_'.$surveyid]['startdate']=$value;
            }
            else
            {
                //Only make session variables for those in insertarray[]
                if (in_array($column, $_SESSION['survey_'.$surveyid]['insertarray']) && isset($_SESSION['survey_'.$surveyid]['fieldmap'][$column]))
                {
                    if (($_SESSION['survey_'.$surveyid]['fieldmap'][$column]['type'] == 'N' ||
                    $_SESSION['survey_'.$surveyid]['fieldmap'][$column]['type'] == 'K' ||
                    $_SESSION['survey_'.$surveyid]['fieldmap'][$column]['type'] == 'D') && $value == null)
                    {   // For type N,K,D NULL in DB is to be considered as NoAnswer in any case.
                        // We need to set the _SESSION[field] value to '' in order to evaluate conditions.
                        // This is especially important for the deletenonvalue feature,
                        // otherwise we would erase any answer with condition such as EQUALS-NO-ANSWER on such
                        // question types (NKD)
                        $_SESSION['survey_'.$surveyid][$column]='';
                    }
                    else
                    {
                        $_SESSION['survey_'.$surveyid][$column]=$value;
                    }
                }  // if (in_array(
            }  // else
        } // foreach
        return true;
    }
}

function makegraph($step, $total)
{
    global $thissurvey;


    Yii::app()->getClientScript()->registerCssFile(Yii::app()->getConfig('publicstyleurl') . 'lime-progress.css');
    $size = intval(($step)/$total*100);

    $graph = '<script type="text/javascript">
    $(document).ready(function() {
    $("#progressbar").progressbar({
    value: '.$size.'
    });
    ;});';
    if (App()->getLocale()->orientation == 'rtl')
    {
        $graph.='
        $(document).ready(function() {
        $("div.ui-progressbar-value").removeClass("ui-corner-left");
        $("div.ui-progressbar-value").addClass("ui-corner-right");
        });';
    }
    $graph.='
    </script>

    <div id="progress-wrapper">
    <span class="hide">'.sprintf(gT('You have completed %s%% of this survey'),$size).'</span>
    <div id="progress-pre">';
    if (App()->getLocale()->orientation == 'rtl')
    {
        $graph.='100%';
    }
    else
    {
        $graph.='0%';
    }

    $graph.='</div>
    <div id="progressbar"></div>
    <div id="progress-post">';
    if (App()->getLocale()->orientation == 'rtl')
    {
        $graph.='0%';
    }
    else
    {
        $graph.='100%';
    }
    $graph.='</div>
    </div>';

    if ($size == 0) // Progress bar looks dumb if 0

    {
        $graph.='
        <script type="text/javascript">
        $(document).ready(function() {
        $("div.ui-progressbar-value").hide();
        });
        </script>';
    }

    return $graph;
}

/**
* This function creates the language selector for a particular survey
*
* @param mixed $sSelectedLanguage The language in which all information is shown
*/
function makeLanguageChangerSurvey($sSelectedLanguage)
{
    $surveyid = App()->surveySessionManager->current->surveyId;
    Yii::app()->loadHelper("surveytranslator");

    $aSurveyLangs = Survey::model()->findByPk($surveyid)->getAllLanguages();
    if (count($aSurveyLangs)>1) // return a dropdow only of there are more than one lanagage
    {
        $aAllLanguages=getLanguageData(true);
        $aSurveyLangs=array_intersect_key($aAllLanguages,array_flip($aSurveyLangs)); // Sort languages by their locale name
        $sClass="languagechanger";
        $sHTMLCode="";
        $sAction=Yii::app()->request->getParam('action','');// Different behaviour if preview
        $sSelected="";
        if(substr($sAction,0,7)=='preview')
        {
            $route="/survey/index/sid/{$surveyid}";
            if ($sAction=='previewgroup' && intval(Yii::app()->request->getParam('gid',0)))
            {
                $route.="/action/previewgroup/gid/".intval(Yii::app()->request->getParam('gid',0));
            }
            if ($sAction=='previewquestion' && intval(Yii::app()->request->getParam('gid',0)) && intval(Yii::app()->request->getParam('qid',0)))
            {
                $route.="/action/previewquestion/gid/".intval(Yii::app()->request->getParam('gid',0))."/qid/".intval(Yii::app()->request->getParam('qid',0));
            }
            if (!is_null(Yii::app()->request->getParam('token')))
            {
                $route.="/token/".Yii::app()->request->getParam('token');
            }
            $sClass.=" previewmode";
            // Maybe add other param (for prefilling by URL): then need a real createUrl with array
#            foreach ($aSurveyLangs as $sLangCode => $aSurveyLang)
#            {
#                $sTargetURL=Yii::app()->getController()->createUrl($route."/lang/$sLangCode");
#                $aListLang[$sTargetURL]=html_entity_decode($aSurveyLang['nativedescription'], ENT_COMPAT,'UTF-8');
#                if(App()->language==$sLangCode)
#                    $sSelected=$sTargetURL;
#            }
        }
        else
        {
            $route="/survey/index/sid/{$surveyid}";
        }
        $sTargetURL=Yii::app()->getController()->createUrl($route);
        foreach ($aSurveyLangs as $sLangCode => $aSurveyLang)
        {
            $aListLang[$sLangCode]=html_entity_decode($aSurveyLang['nativedescription'], ENT_COMPAT,'UTF-8');
        }
        $sSelected=App()->language;
        $sHTMLCode=CHtml::label(gT("Choose another language"), 'lang',array('class'=>'hide label'));
        $sHTMLCode.=CHtml::dropDownList('lang', $sSelected,$aListLang,array('class'=>$sClass,'data-targeturl'=>$sTargetURL));
        // We don't have to add this button if in previewmode
        $sHTMLCode.= CHtml::htmlButton(gT("Change the language"),array('type'=>'submit','id'=>"changelangbtn",'value'=>'changelang','name'=>'changelang','class'=>'changelang jshide'));
        return $sHTMLCode;
    }
    else
    {
        return false;
    }

}

/**
* This function creates the language selector for the public survey index page
*
* @param mixed $sSelectedLanguage The language in which all information is shown
*/
function makeLanguageChanger($sSelectedLanguage)
{
    $aLanguages=getLanguageDataRestricted(true,$sSelectedLanguage);// Order by native
    if(count($aLanguages)>1)
    {
#        $sHTMLCode = "<select id='languagechanger' name='languagechanger' class='languagechanger' onchange='javascript:window.location=this.value'>\n";
#        foreach(getLanguageDataRestricted(true, $sSelectedLanguage) as $sLanguageID=>$aLanguageProperties)
#        {
#            $sLanguageUrl=Yii::app()->getController()->createUrl('survey/index',array('lang'=>$sLanguageID));
#            $sHTMLCode .= "<option value='{$sLanguageUrl}'";
#            if($sLanguageID == $sSelectedLanguage)
#            {
#                $sHTMLCode .= " selected='selected' ";
#                $sHTMLCode .= ">{$aLanguageProperties['nativedescription']}</option>\n";
#            }
#            else
#            {
#                $sHTMLCode .= ">".$aLanguageProperties['nativedescription'].' - '.$aLanguageProperties['description']."</option>\n";
#            }
#        }
#        $sHTMLCode .= "</select>\n";

        $sClass= "languagechanger";
        foreach ($aLanguages as $sLangCode => $aLanguage)
            $aListLang[$sLangCode]=html_entity_decode($aLanguage['nativedescription'], ENT_COMPAT,'UTF-8').' - '.$aLanguage['description'];
        $sSelected=$sSelectedLanguage;
        $sHTMLCode= CHtml::beginForm(App()->createUrl('surveys/publiclist'),'get');
        $sHTMLCode.=CHtml::label(gT("Choose another language"), 'lang',array('class'=>'hide label'));
        $sHTMLCode.= CHtml::dropDownList('lang', $sSelected,$aListLang,array('class'=>$sClass));
        //$sHTMLCode.= CHtml::htmlButton(gT("Change the language"),array('type'=>'submit','id'=>"changelangbtn",'value'=>'changelang','name'=>'changelang','class'=>'jshide'));
        $sHTMLCode.="<button class='changelang jshide' value='changelang' id='changelangbtn' type='submit'>".gT("Change the language")."</button>";
        $sHTMLCode.= CHtml::endForm();
        return $sHTMLCode;
    }
    else
    {
        return false;
    }
}



/**
* checkUploadedFileValidity used in SurveyRuntimeHelper
*/
function checkUploadedFileValidity($surveyid, $move, $backok=null)
{
    return false;
    $session = App()->surveySessionManager->current;

    if (!isset($backok) || $backok != "Y")
    {
        $fieldmap = createFieldMap($surveyid,'full',false,false,$session->language);

        if (isset($_POST['fieldnames']) && $_POST['fieldnames']!="")
        {
            $fields = explode("|", $_POST['fieldnames']);

            foreach ($fields as $field)
            {
                if ($fieldmap[$field]['type'] == "|" && !strrpos($fieldmap[$field]['fieldname'], "_filecount"))
                {
                    $validation= \QuestionAttribute::model()->getQuestionAttributes($fieldmap[$field]['qid']);

                    $filecount = 0;

                    $json = $_POST[$field];
                    // if name is blank, its basic, hence check
                    // else, its ajax, don't check, bypass it.

                    if ($json != "" && $json != "[]")
                    {
                        $phparray = json_decode(stripslashes($json));
                        if ($phparray[0]->size != "")
                        { // ajax
                            $filecount = count($phparray);
                        }
                        else
                        { // basic
                            for ($i = 1; $i <= $validation['max_num_of_files']; $i++)
                            {
                                if (!isset($_FILES[$field."_file_".$i]) || $_FILES[$field."_file_".$i]['name'] == '')
                                    continue;

                                $filecount++;

                                $file = $_FILES[$field."_file_".$i];

                                // File size validation
                                if ($file['size'] > $validation['max_filesize'] * 1000)
                                {
                                    $filenotvalidated = array();
                                    $filenotvalidated[$field."_file_".$i] = sprintf(gT("Sorry, the uploaded file (%s) is larger than the allowed filesize of %s KB."), $file['size'], $validation['max_filesize']);
                                    $append = true;
                                }

                                // File extension validation
                                $pathinfo = pathinfo(basename($file['name']));
                                $ext = $pathinfo['extension'];

                                $validExtensions = explode(",", $validation['allowed_filetypes']);
                                if (!(in_array($ext, $validExtensions)))
                                {
                                    if (isset($append) && $append)
                                    {
                                        $filenotvalidated[$field."_file_".$i] .= sprintf(gT("Sorry, only %s extensions are allowed!"),$validation['allowed_filetypes']);
                                        unset($append);
                                    }
                                    else
                                    {
                                        $filenotvalidated = array();
                                        $filenotvalidated[$field."_file_".$i] .= sprintf(gT("Sorry, only %s extensions are allowed!"),$validation['allowed_filetypes']);
                                    }
                                }
                            }
                        }
                    }
                    else
                        $filecount = 0;

                    if (isset($validation['min_num_of_files']) && $filecount < $validation['min_num_of_files'] && LimeExpressionManager::QuestionIsRelevant($fieldmap[$field]['qid']))
                    {
                        $filenotvalidated = array();
                        $filenotvalidated[$field] = gT("The minimum number of files has not been uploaded.");
                    }
                }
            }
        }
        if (isset($filenotvalidated))
        {
            if (isset($move) &&
                ($move == "moveprev" || $move == "movenext"))
                App()->surveySessionManager->current->setStep($thisstep);
            return $filenotvalidated;
        }
    }
    if (!isset($filenotvalidated))
        return false;
    else
        return $filenotvalidated;
}

/**
* Takes two single element arrays and adds second to end of first if value exists
* Why not use array_merge($array1,array_filter($array2);
*/
function addtoarray_single($array1, $array2)
{
    //
    if (is_array($array2))
    {
        foreach ($array2 as $ar)
        {
            if ($ar && $ar !== null)
            {
                $array1[]=$ar;
            }
        }
    }
    return $array1;
}

/**
* Marks a tokens as completed and sends a confirmation email to the participiant.
* If $quotaexit is set to true then the user exited the survey due to a quota
* restriction and the according token is only marked as 'Q'
*
* @param mixed $quotaexit
*/
function submittokens($quotaexit=false)
{
    $session = App()->surveySessionManager->current;
    $survey = $session->survey;
    $token = $session->response->tokenObject;


    if ($quotaexit==true)
    {
        $token->completed = 'Q';
        $token->usesleft--;
    }
    else
    {
        if ($token->usesleft <= 1)
        {
            // Finish the token
            if (!$token->survey->bool_anonymized)
            {
                $token->completed = date('Y-m-d');
            } else {
                $token->completed = 'Y';
            }
            if(isset($token->participant_id))
            {
                $surveyLink = SurveyLink::model()->findByAttributes([
                    'participant_id' => $token->participant_id,
                    'survey_id' => $survey->primaryKey,
                    'token_id' => $token->primaryKey
                ]);
                if (isset($surveyLink))
                {
                    if ($token->survey->bool_anonymized)
                    {
                        $surveyLink->date_completed = date('Y-m-d');
                    } else {
                        // Update the survey_links table if necessary, to protect anonymity, use the date_created field date
                        $surveyLink->date_completed = $surveyLink->date_created;
                    }
                    $surveyLink->save();
                }
            }
        }
        $token->usesleft--;
    }
    $token->save();

    if ($quotaexit==false)
    {
        if (trim(strip_tags($survey->localizedConfirmationEmail)) != "" && $survey->bool_sendconfirmation)
        {
         //   if($token->completed == "Y" || $token->completed == $today)
//            {
                $from = "{$survey->admin} <{$survey->adminemail}>";
                $subject= $survey->localizedConfirmationEmailSubject;

                $aReplacementVars= [];
                $aReplacementVars["ADMINNAME"] =  $survey->admin;
                $aReplacementVars["ADMINEMAIL"] = $survey->adminEmail;
                //Fill with token info, because user can have his information with anonimity control
                $aReplacementVars["FIRSTNAME"] = $token->firstname;
                $aReplacementVars["LASTNAME"] = $token->lastname;
                $aReplacementVars["TOKEN"] = $token->token;
                // added survey url in replacement vars
                $aReplacementVars['SURVEYURL'] = App()->createAbsoluteUrl("survey/index", [
                    'lang' => $session->language,
                    'token' => $token->token,
                    'sid' => $survey->primaryKey
                ]);

                $attrfieldnames = $token->customAttributeNames();
                foreach ($attrfieldnames as $attr_name)
                {
                    $aReplacementVars[strtoupper($attr_name)] = $token->$attr_name;
                }

                $dateformatdatat = getDateFormatData($survey->getLocalizedDateFormat());
                $numberformatdatat = getRadixPointData($survey->getLocalizedNumberFormat());
                $redata = [];
                $subject=templatereplace($subject, $aReplacementVars, $redata, 'email_confirm_subj', null, true);

                $subject = html_entity_decode($subject,ENT_QUOTES);

                $ishtml = $survey->bool_htmlemail;

                $message = html_entity_decode(
                    templatereplace($survey->getLocalizedConfirmationEmail(), $aReplacementVars, $redata,
                        'email_confirm', null, true),
                    ENT_QUOTES
                );
                if (!$ishtml)
                {
                    $message=strip_tags(breakToNewline($message));
                }

                //Only send confirmation email if there is a valid email address
            $sToAddress=validateEmailAddresses($token->email);
            if ($sToAddress) {
                $aAttachments = unserialize($survey->getLocalizedAttachments());

                $aRelevantAttachments = array();
                /*
                 * Iterate through attachments and check them for relevance.
                 */
                if (isset($aAttachments['confirmation']))
                {
                    foreach ($aAttachments['confirmation'] as $aAttachment)
                    {
                        $relevance = $aAttachment['relevance'];
                        // If the attachment is relevant it will be added to the mail.
                        if (LimeExpressionManager::ProcessRelevance($relevance) && file_exists($aAttachment['url']))
                        {
                            $aRelevantAttachments[] = $aAttachment['url'];
                        }
                    }
                }
                SendEmailMessage($message, $subject, $sToAddress, $from, SettingGlobal::get('sitename'), $ishtml, null, $aRelevantAttachments);
            }
     //   } else {
                // Leave it to send optional confirmation at closed token
  //          }
        }
    }
}

/**
* Send a submit notification to the email address specified in the notifications tab in the survey settings
*/
function sendSubmitNotifications($surveyid)
{
    // @todo: Remove globals
    global $thissurvey, $maildebug, $tokensexist;

    if (trim($thissurvey['adminemail'])=='')
    {
        return;
    }

    $homeurl=Yii::app()->createAbsoluteUrl('/admin');

    $sitename = Yii::app()->getConfig("sitename");

    $debug=Yii::app()->getConfig('debug');
    $bIsHTML = ($thissurvey['htmlemail'] == 'Y');

    $aReplacementVars=array();

    if ($thissurvey['allowsave'] == "Y" && isset($_SESSION['survey_'.$surveyid]['scid']))
    {
        $aReplacementVars['RELOADURL']="".Yii::app()->getController()->createUrl("/survey/index/sid/{$surveyid}/loadall/reload/scid/".$_SESSION['survey_'.$surveyid]['scid']."/loadname/".urlencode($_SESSION['survey_'.$surveyid]['holdname'])."/loadpass/".urlencode($_SESSION['survey_'.$surveyid]['holdpass'])."/lang/".urlencode(App()->language));
        if ($bIsHTML)
        {
            $aReplacementVars['RELOADURL']="<a href='{$aReplacementVars['RELOADURL']}'>{$aReplacementVars['RELOADURL']}</a>";
        }
    }
    else
    {
        $aReplacementVars['RELOADURL']='';
    }

    if (!isset($_SESSION['survey_'.$surveyid]['srid']))
        $srid = null;
    else
        $srid = $_SESSION['survey_'.$surveyid]['srid'];
    $aReplacementVars['ADMINNAME'] = $thissurvey['adminname'];
    $aReplacementVars['ADMINEMAIL'] = $thissurvey['adminemail'];
    $aReplacementVars['VIEWRESPONSEURL']=Yii::app()->createAbsoluteUrl("/admin/responses/sa/view/surveyid/{$surveyid}/id/{$srid}");
    $aReplacementVars['EDITRESPONSEURL']=Yii::app()->createAbsoluteUrl("/admin/dataentry/sa/editdata/subaction/edit/surveyid/{$surveyid}/id/{$srid}");
    $aReplacementVars['STATISTICSURL']=Yii::app()->createAbsoluteUrl("/admin/statistics/sa/index/surveyid/{$surveyid}");
    if ($bIsHTML)
    {
        $aReplacementVars['VIEWRESPONSEURL']="<a href='{$aReplacementVars['VIEWRESPONSEURL']}'>{$aReplacementVars['VIEWRESPONSEURL']}</a>";
        $aReplacementVars['EDITRESPONSEURL']="<a href='{$aReplacementVars['EDITRESPONSEURL']}'>{$aReplacementVars['EDITRESPONSEURL']}</a>";
        $aReplacementVars['STATISTICSURL']="<a href='{$aReplacementVars['STATISTICSURL']}'>{$aReplacementVars['STATISTICSURL']}</a>";
    }
    $aReplacementVars['ANSWERTABLE']='';
    $aEmailResponseTo=array();
    $aEmailNotificationTo=array();
    $sResponseData="";

    if (!empty($thissurvey['emailnotificationto']))
    {
        $aRecipient=explode(";", ReplaceFields($thissurvey['emailnotificationto'],array('ADMINEMAIL' =>$thissurvey['adminemail'] ), true));
        foreach($aRecipient as $sRecipient)
        {
            $sRecipient=trim($sRecipient);
            if(validateEmailAddress($sRecipient))
            {
                $aEmailNotificationTo[]=$sRecipient;
            }
        }
    }

    if (!empty($thissurvey['emailresponseto']))
    {
        // there was no token used so lets remove the token field from insertarray
        if (!isset($_SESSION['survey_'.$surveyid]['token']) && $_SESSION['survey_'.$surveyid]['insertarray'][0]=='token')
        {
            unset($_SESSION['survey_'.$surveyid]['insertarray'][0]);
        }
        //Make an array of email addresses to send to
        $aRecipient=explode(";", ReplaceFields($thissurvey['emailresponseto'],array('ADMINEMAIL' =>$thissurvey['adminemail'] ), true));
        foreach($aRecipient as $sRecipient)
        {
            $sRecipient=trim($sRecipient);
            if(validateEmailAddress($sRecipient))
            {
                $aEmailResponseTo[]=$sRecipient;
            }
        }

        $aFullResponseTable=getFullResponseTable($surveyid,$_SESSION['survey_'.$surveyid]['srid'],$session->language);
        $ResultTableHTML = "<table class='printouttable' >\n";
        $ResultTableText ="\n\n";
        $oldgid = 0;
        $oldqid = 0;
        foreach ($aFullResponseTable as $sFieldname=>$fname)
        {
            if (substr($sFieldname,0,4)=='gid_')
            {
                $ResultTableHTML .= "\t<tr class='printanswersgroup'><td colspan='2'>".strip_tags($fname[0])."</td></tr>\n";
                $ResultTableText .="\n{$fname[0]}\n\n";
            }
            elseif (substr($sFieldname,0,4)=='qid_')
            {
                $ResultTableHTML .= "\t<tr class='printanswersquestionhead'><td  colspan='2'>".strip_tags($fname[0])."</td></tr>\n";
                $ResultTableText .="\n{$fname[0]}\n";
            }
            else
            {
                $ResultTableHTML .= "\t<tr class='printanswersquestion'><td>".strip_tags("{$fname[0]} {$fname[1]}")."</td><td class='printanswersanswertext'>".CHtml::encode($fname[2])."</td></tr>\n";
                $ResultTableText .="     {$fname[0]} {$fname[1]}: {$fname[2]}\n";
            }
        }

        $ResultTableHTML .= "</table>\n";
        $ResultTableText .= "\n\n";
        if ($bIsHTML)
        {
            $aReplacementVars['ANSWERTABLE']=$ResultTableHTML;
        }
        else
        {
            $aReplacementVars['ANSWERTABLE']=$ResultTableText;
        }
    }

    $sFrom = $thissurvey['adminname'].' <'.$thissurvey['adminemail'].'>';

    $aAttachments = unserialize($thissurvey['attachments']);

    $aRelevantAttachments = array();
    /*
     * Iterate through attachments and check them for relevance.
     */
    if (isset($aAttachments['admin_notification']))
    {
        foreach ($aAttachments['admin_notification'] as $aAttachment)
        {
            $relevance = $aAttachment['relevance'];
            // If the attachment is relevant it will be added to the mail.
            if (LimeExpressionManager::ProcessRelevance($relevance) && file_exists($aAttachment['url']))
            {
                $aRelevantAttachments[] = $aAttachment['url'];
            }
        }
    }

    $redata=compact(array_keys(get_defined_vars()));
    if (count($aEmailNotificationTo)>0)
    {
        $sMessage=templatereplace($thissurvey['email_admin_notification'], $aReplacementVars, $redata,
            'admin_notification', null, true);
        $sSubject=templatereplace($thissurvey['email_admin_notification_subj'], $aReplacementVars, $redata,
            'admin_notification_subj', null, true);
        foreach ($aEmailNotificationTo as $sRecipient)
        {
        if (!SendEmailMessage($sMessage, $sSubject, $sRecipient, $sFrom, $sitename, true, getBounceEmail($surveyid), $aRelevantAttachments))
            {
                if ($debug>0)
                {
                    echo '<br />Email could not be sent. Reason: '.$maildebug.'<br/>';
                }
            }
        }
    }

        $aRelevantAttachments = array();
    /*
     * Iterate through attachments and check them for relevance.
     */
    if (isset($aAttachments['detailed_admin_notification']))
    {
        foreach ($aAttachments['detailed_admin_notification'] as $aAttachment)
        {
            $relevance = $aAttachment['relevance'];
            // If the attachment is relevant it will be added to the mail.
            if (LimeExpressionManager::ProcessRelevance($relevance) && file_exists($aAttachment['url']))
            {
                $aRelevantAttachments[] = $aAttachment['url'];
            }
        }
    }
    if (count($aEmailResponseTo)>0)
    {
        $sMessage=templatereplace($thissurvey['email_admin_responses'], $aReplacementVars, $redata,
            'detailed_admin_notification', null, true);
        $sSubject=templatereplace($thissurvey['email_admin_responses_subj'], $aReplacementVars, $redata,
            'detailed_admin_notification_subj', null, true);
        foreach ($aEmailResponseTo as $sRecipient)
        {
        if (!SendEmailMessage($sMessage, $sSubject, $sRecipient, $sFrom, $sitename, true, getBounceEmail($surveyid), $aRelevantAttachments))
            {
                if ($debug>0)
                {
                    echo '<br />Email could not be sent. Reason: '.$maildebug.'<br/>';
                }
            }
        }
    }


}

/**
* submitfailed : used in em_manager_helper.php
*/
function submitfailed($errormsg='')
{
    throw new \Exception("Submit failed: " . $errormsg);
    global $debug;
    global $thissurvey;
    global $subquery, $surveyid;



    $completed = "<br /><strong><font size='2' color='red'>"
    . gT("Did Not Save")."</strong></font><br /><br />\n\n"
    . gT("An unexpected error has occurred and your responses cannot be saved.")."<br /><br />\n";
    if ($thissurvey['adminemail'])
    {
        $completed .= gT("Your responses have not been lost and have been emailed to the survey administrator and will be entered into our database at a later point.")."<br /><br />\n";
        if ($debug>0)
        {
            $completed.='Error message: '.htmlspecialchars($errormsg).'<br />';
        }
        $email=gT("An error occurred saving a response to survey id","unescaped")." ".$thissurvey['name']." - $surveyid\n\n";
        $email .= gT("DATA TO BE ENTERED","unescaped").":\n";
        foreach ($_SESSION['survey_'.$surveyid]['insertarray'] as $value)
        {
            $email .= "$value: {$_SESSION['survey_'.$surveyid][$value]}\n";
        }
        $email .= "\n".gT("SQL CODE THAT FAILED","unescaped").":\n"
        . "$subquery\n\n"
        . gT("ERROR MESSAGE","unescaped").":\n"
        . $errormsg."\n\n";
        SendEmailMessage($email, gT("Error saving results","unescaped"), $thissurvey['adminemail'], $thissurvey['adminemail'], "LimeSurvey", false, getBounceEmail($surveyid));
        //echo "<!-- EMAIL CONTENTS:\n$email -->\n";
        //An email has been sent, so we can kill off this session.
        killSurveySession($surveyid);
    }
    else
    {
        $completed .= "<a href='javascript:location.reload()'>".gT("Try to submit again")."</a><br /><br />\n";
        $completed .= $subquery;
    }
    return $completed;
}

/**
* This function builds all the required session variables when a survey is first started and
* it loads any answer defaults from command line or from the table defaultvalues
* It is called from the related format script (group.php, question.php, survey.php)
* if the survey has just started.
*/
function buildsurveysession($surveyid,$preview=false)
{
    bP();
    global $secerror, $clienttoken;
    global $tokensexist;
    //global $surveyid;
    global $move, $rooturl;
    $session = App()->surveySessionManager->current;

    $sLangCode=App()->language;
    $languagechanger=makeLanguageChangerSurvey($sLangCode);
    if(!$preview)
        $preview=Yii::app()->getConfig('previewmode');
    $thissurvey = getSurveyInfo($surveyid,$sLangCode);


    $loadsecurity = returnGlobal('loadsecurity',true);

    // NO TOKEN REQUIRED BUT CAPTCHA ENABLED FOR SURVEY ACCESS
    if ($tokensexist == 0 && isCaptchaEnabled('surveyaccessscreen',$thissurvey['usecaptcha']) && !isset($_SESSION['survey_'.$surveyid]['captcha_surveyaccessscreen'])&& !$preview)
    {
        // IF CAPTCHA ANSWER IS NOT CORRECT OR NOT SET
        if (!isset($loadsecurity) ||
        !isset($_SESSION['survey_'.$surveyid]['secanswer']) ||
        $loadsecurity != $_SESSION['survey_'.$surveyid]['secanswer'])
        {
            sendCacheHeaders();
            doHeader();
            // No or bad answer to required security question

            $redata = compact(array_keys(get_defined_vars()));
            renderOldTemplate($sTemplatePath . "startpage.pstpl", array(), $redata,
                'frontend_helper[875]');
            //echo makedropdownlist();
            renderOldTemplate($sTemplatePath . "survey.pstpl", array(), $redata,
                'frontend_helper[877]');

            if (isset($loadsecurity))
            { // was a bad answer
                echo "<font color='#FF0000'>".gT("The answer to the security question is incorrect.")."</font><br />";
            }

            echo "<p class='captcha'>".gT("Please confirm access to survey by answering the security question below and click continue.")."</p>"
            .CHtml::form(array("/survey/index","sid"=>$surveyid), 'post', array('class'=>'captcha'))."
            <table align='center'>
            <tr>
            <td align='right' valign='middle'>
            <input type='hidden' name='sid' value='".$surveyid."' id='sid' />
            <input type='hidden' name='lang' value='".$sLangCode."' id='lang' />";
            // In case we this is a direct Reload previous answers URL, then add hidden fields
            if (isset($_GET['loadall']) && isset($_GET['scid'])
            && isset($_GET['loadname']) && isset($_GET['loadpass']))
            {
                echo "
                <input type='hidden' name='loadall' value='".htmlspecialchars($_GET['loadall'],ENT_QUOTES, 'UTF-8')."' id='loadall' />
                <input type='hidden' name='scid' value='".returnGlobal('scid',true)."' id='scid' />
                <input type='hidden' name='loadname' value='".htmlspecialchars($_GET['loadname'],ENT_QUOTES, 'UTF-8')."' id='loadname' />
                <input type='hidden' name='loadpass' value='".htmlspecialchars($_GET['loadpass'],ENT_QUOTES, 'UTF-8')."' id='loadpass' />";
            }

            echo "
            </td>
            </tr>";
            if (function_exists("ImageCreate") && isCaptchaEnabled('surveyaccessscreen', $thissurvey['usecaptcha']))
            {
                echo "<tr>
                <td align='center' valign='middle'><label for='captcha'>".gT("Security question:")."</label></td><td align='left' valign='middle'><table><tr><td valign='middle'><img src='".Yii::app()->getController()->createUrl('/verification/image/sid/'.$surveyid)."' alt='captcha' /></td>
                <td valign='middle'><input id='captcha' type='text' size='5' maxlength='3' name='loadsecurity' value='' /></td></tr></table>
                </td>
                </tr>";
            }
            echo "<tr><td colspan='2' align='center'><input class='submit' type='submit' value='".gT("Continue")."' /></td></tr>
            </table>
            </form>";

            renderOldTemplate($sTemplatePath . "endpage.pstpl", array(), $redata,
                'frontend_helper[1567]');
            doFooter();
            exit;
        }
        else{
            $_SESSION['survey_'.$surveyid]['captcha_surveyaccessscreen']=true;
        }

    }

    //BEFORE BUILDING A NEW SESSION FOR THIS SURVEY, LET'S CHECK TO MAKE SURE THE SURVEY SHOULD PROCEED!
    // TOKEN REQUIRED BUT NO TOKEN PROVIDED
    if ($tokensexist == 1 && !$clienttoken && !$preview)
    {

        if ($thissurvey['nokeyboard']=='Y')
        {
            includeKeypad();
            $kpclass = "text-keypad";
        }
        else
        {
            $kpclass = "";
        }

        // DISPLAY REGISTER-PAGE if needed
        // DISPLAY CAPTCHA if needed
         if (isset($thissurvey) && $thissurvey['allowregister'] == "Y")
         {
            // Add the event and test if done
            Yii::app()->runController("register/index/sid/{$surveyid}");
            Yii::app()->end();
        }
        else
        {
            sendCacheHeaders();
            doHeader();
            $redata = compact(array_keys(get_defined_vars()));
            renderOldTemplate($sTemplatePath . "startpage.pstpl", array(), $redata,
                'frontend_helper[1594]');
            //echo makedropdownlist();
            renderOldTemplate($sTemplatePath . "survey.pstpl", array(), $redata,
                'frontend_helper[1596]');
            // ->renderPartial('entertoken_view');
            if (isset($secerror)) echo "<span class='error'>".$secerror."</span><br />";
            echo '<div id="wrapper"><p id="tokenmessage">'.gT("This is a controlled survey. You need a valid token to participate.")."<br />";
            echo gT("If you have been issued a token, please enter it in the box below and click continue.")."</p>
            <script type='text/javascript'>var focus_element='#token';</script>"
            .CHtml::form(array("/survey/index","sid"=>$surveyid), 'post', array('id'=>'tokenform','autocomplete'=>'off'))."
            <ul>
            <li>";?>
            <label for='token'><?php eT("Token:");?></label><input class='text <?php echo $kpclass?>' id='token' type='password' name='token' value='' />
            <?php
            echo "<input type='hidden' name='sid' value='".$surveyid."' id='sid' />
            <input type='hidden' name='lang' value='".$sLangCode."' id='lang' />";
            if (isset($_GET['newtest']) && $_GET['newtest'] == "Y")
            {
                echo "  <input type='hidden' name='newtest' value='Y' id='newtest' />";

            }

            // If this is a direct Reload previous answers URL, then add hidden fields
            if (isset($_GET['loadall']) && isset($_GET['scid'])
            && isset($_GET['loadname']) && isset($_GET['loadpass']))
            {
                echo "
                <input type='hidden' name='loadall' value='".htmlspecialchars($_GET['loadall'],ENT_QUOTES, 'UTF-8')."' id='loadall' />
                <input type='hidden' name='scid' value='".returnGlobal('scid',true)."' id='scid' />
                <input type='hidden' name='loadname' value='".htmlspecialchars($_GET['loadname'],ENT_QUOTES, 'UTF-8')."' id='loadname' />
                <input type='hidden' name='loadpass' value='".htmlspecialchars($_GET['loadpass'],ENT_QUOTES, 'UTF-8')."' id='loadpass' />";
            }
            echo "</li>";

            if (function_exists("ImageCreate") && isCaptchaEnabled('surveyaccessscreen', $thissurvey['usecaptcha']))
            {
                echo "<li>
                <label for='captchaimage'>".gT("Security Question")."</label><img id='captchaimage' src='".Yii::app()->getController()->createUrl('/verification/image/sid/'.$surveyid)."' alt='captcha' /><input type='text' size='5' maxlength='3' name='loadsecurity' value='' />
                </li>";
            }
            echo "<li>
            <input class='submit button' type='submit' value='".gT("Continue")."' />
            </li>
            </ul>
            </form></div>";
            renderOldTemplate($sTemplatePath . "endpage.pstpl", array(), $redata,
                'frontend_helper[1645]');
            doFooter();
            exit;
        }
    }
    // TOKENS REQUIRED, A TOKEN PROVIDED
    // SURVEY WITH NO NEED TO USE CAPTCHA
    elseif ($tokensexist == 1 && $clienttoken &&
    !isCaptchaEnabled('surveyaccessscreen',$thissurvey['usecaptcha']))
    {

        //check if token actually does exist
        // check also if it is allowed to change survey after completion
        if ($thissurvey['alloweditaftercompletion'] == 'Y' ) {
            $oTokenEntry = Token::model($surveyid)->findByAttributes(array('token'=>$clienttoken));
        } else {
            $oTokenEntry = Token::model($surveyid)->usable()->incomplete()->findByAttributes(array('token' => $clienttoken));
        }
        if (!isset($oTokenEntry))
        {
            //TOKEN DOESN'T EXIST OR HAS ALREADY BEEN USED. EXPLAIN PROBLEM AND EXIT

            killSurveySession($surveyid);
            sendCacheHeaders();
            doHeader();

            $redata = compact(array_keys(get_defined_vars()));
            renderOldTemplate($sTemplatePath . "startpage.pstpl", array(), $redata,
                'frontend_helper[1676]');
            renderOldTemplate($sTemplatePath . "survey.pstpl", array(), $redata,
                'frontend_helper[1677]');
            echo '<div id="wrapper"><p id="tokenmessage">'.gT("This is a controlled survey. You need a valid token to participate.")."<br /><br />\n"
            ."\t".gT("The token you have provided is either not valid, or has already been used.")."<br /><br />\n"
            ."\t".sprintf(gT("For further information please contact %s"), $thissurvey['adminname'])
            ." (<a href='mailto:{$thissurvey['adminemail']}'>"
            ."{$thissurvey['adminemail']}</a>)</p></div>\n";

            renderOldTemplate($sTemplatePath . "endpage.pstpl", array(), $redata,
                'frontend_helper[1684]');
            doFooter();
            exit;
        }
   }
    // TOKENS REQUIRED, A TOKEN PROVIDED
    // SURVEY CAPTCHA REQUIRED
    elseif ($tokensexist == 1 && $clienttoken && isCaptchaEnabled('surveyaccessscreen',$thissurvey['usecaptcha']))
    {

        // IF CAPTCHA ANSWER IS CORRECT
        if (isset($loadsecurity) &&
        isset($_SESSION['survey_'.$surveyid]['secanswer']) &&
        $loadsecurity == $_SESSION['survey_'.$surveyid]['secanswer'])
        {
            if ($thissurvey['alloweditaftercompletion'] == 'Y' )
            {
                $oTokenEntry = Token::model($surveyid)->findByAttributes(array('token'=> $clienttoken));
            }
            else
            {
                $oTokenEntry = Token::model($surveyid)->incomplete()->findByAttributes(array(
                    'token' => $clienttoken
                ));
           }
            if (!isset($oTokenEntry))
            {
                sendCacheHeaders();
                doHeader();
                //TOKEN DOESN'T EXIST OR HAS ALREADY BEEN USED. EXPLAIN PROBLEM AND EXIT

                $redata = compact(array_keys(get_defined_vars()));
                renderOldTemplate($sTemplatePath . "startpage.pstpl", array(), $redata,
                    'frontend_helper[1719]');
                renderOldTemplate($sTemplatePath . "survey.pstpl", array(), $redata,
                    'frontend_helper[1720]');
                echo "\t<div id='wrapper'>\n"
                ."\t<p id='tokenmessage'>\n"
                ."\t".gT("This is a controlled survey. You need a valid token to participate.")."<br /><br />\n"
                ."\t".gT("The token you have provided is either not valid, or has already been used.")."<br/><br />\n"
                ."\t".sprintf(gT("For further information please contact %s"), $thissurvey['adminname'])
                ." (<a href='mailto:{$thissurvey['adminemail']}'>"
                ."{$thissurvey['adminemail']}</a>)\n"
                ."\t</p>\n"
                ."\t</div>\n";

                renderOldTemplate($sTemplatePath . "endpage.pstpl", array(), $redata,
                    'frontend_helper[1731]');
                doFooter();
                exit;
            }
        }
        // IF CAPTCHA ANSWER IS NOT CORRECT
        else if (!isset($move) || is_null($move))
            {
                unset($_SESSION['survey_'.$surveyid]['srid']);
                $gettoken = $clienttoken;
                sendCacheHeaders();
                doHeader();
                // No or bad answer to required security question
                $redata = compact(array_keys(get_defined_vars()));
                renderOldTemplate($sTemplatePath . "startpage.pstpl", array(), $redata,
                    'frontend_helper[1745]');
                renderOldTemplate($sTemplatePath . "survey.pstpl", array(), $redata,
                    'frontend_helper[1746]');
                // If token wasn't provided and public registration
                // is enabled then show registration form
                if ( !isset($gettoken) && isset($thissurvey) && $thissurvey['allowregister'] == "Y")
                {
                    renderOldTemplate($sTemplatePath . "register.pstpl", array(), $redata,
                        'frontend_helper[1751]');
                }
                else
                { // only show CAPTCHA

                    echo '<div id="wrapper"><p id="tokenmessage">';
                    if (isset($loadsecurity))
                    { // was a bad answer
                        echo "<span class='error'>".gT("The answer to the security question is incorrect.")."</span><br />";
                    }

                    echo gT("This is a controlled survey. You need a valid token to participate.")."<br /><br />";
                    // IF TOKEN HAS BEEN GIVEN THEN AUTOFILL IT
                    // AND HIDE ENTRY FIELD
                    if (!isset($gettoken))
                    {
                        echo gT("If you have been issued a token, please enter it in the box below and click continue.")."</p>
                        <form id='tokenform' method='get' action='".Yii::app()->getController()->createUrl("/survey/index")."'>
                        <ul>
                        <li>
                        <input type='hidden' name='sid' value='".$surveyid."' id='sid' />
                        <input type='hidden' name='lang' value='".$sLangCode."' id='lang' />";
                        if (isset($_GET['loadall']) && isset($_GET['scid'])
                        && isset($_GET['loadname']) && isset($_GET['loadpass']))
                        {
                            echo "<input type='hidden' name='loadall' value='".htmlspecialchars($_GET['loadall'],ENT_QUOTES, 'UTF-8')."' id='loadall' />
                            <input type='hidden' name='scid' value='".returnGlobal('scid',true)."' id='scid' />
                            <input type='hidden' name='loadname' value='".htmlspecialchars($_GET['loadname'],ENT_QUOTES, 'UTF-8')."' id='loadname' />
                            <input type='hidden' name='loadpass' value='".htmlspecialchars($_GET['loadpass'],ENT_QUOTES, 'UTF-8')."' id='loadpass' />";
                        }

                        echo '<label for="token">'.gT("Token")."</label><input class='text' type='password' id='token' name='token'></li>";
                }
                else
                {
                    echo gT("Please confirm the token by answering the security question below and click continue.")."</p>
                    <form id='tokenform' method='get' action='".Yii::app()->getController()->createUrl("/survey/index")."'>
                    <ul>
                    <li>
                    <input type='hidden' name='sid' value='".$surveyid."' id='sid' />
                    <input type='hidden' name='lang' value='".$sLangCode."' id='lang' />";
                    if (isset($_GET['loadall']) && isset($_GET['scid'])
                    && isset($_GET['loadname']) && isset($_GET['loadpass']))
                    {
                        echo "<input type='hidden' name='loadall' value='".htmlspecialchars($_GET['loadall'],ENT_QUOTES, 'UTF-8')."' id='loadall' />
                        <input type='hidden' name='scid' value='".returnGlobal('scid',true)."' id='scid' />
                        <input type='hidden' name='loadname' value='".htmlspecialchars($_GET['loadname'],ENT_QUOTES, 'UTF-8')."' id='loadname' />
                        <input type='hidden' name='loadpass' value='".htmlspecialchars($_GET['loadpass'],ENT_QUOTES, 'UTF-8')."' id='loadpass' />";
                    }
                    echo '<label for="token">'.gT("Token:")."</label><span id='token'>$gettoken</span>"
                    ."<input type='hidden' name='token' value='$gettoken'></li>";
                }


                if (function_exists("ImageCreate") && isCaptchaEnabled('surveyaccessscreen', $thissurvey['usecaptcha']))
                {
                    echo "<li>
                    <label for='captchaimage'>".gT("Security Question")."</label><img id='captchaimage' src='".Yii::app()->getController()->createUrl('/verification/image/sid/'.$surveyid)."' alt='captcha' /><input type='text' size='5' maxlength='3' name='loadsecurity' value='' />
                    </li>";
                }
                echo "<li><input class='submit' type='submit' value='".gT("Continue")."' /></li>
                </ul>
                </form>
                </id>";
            }

            echo '</div>'.templatereplace(file_get_contents($sTemplatePath . "endpage.pstpl"), array(), $redata,
                    'frontend_helper[1817]');
            doFooter();
            exit;
        }
    }

    //RESET ALL THE SESSION VARIABLES AND START AGAIN
    unset($_SESSION['survey_'.$surveyid]['grouplist']);
    unset($_SESSION['survey_'.$surveyid]['insertarray']);
    unset($_SESSION['survey_'.$surveyid]['fieldnamesInfo']);
    $_SESSION['survey_'.$surveyid]['fieldnamesInfo'] = Array();

    // Multi lingual support order : by REQUEST, if not by Token->language else by survey default language
    if (returnGlobal('lang',true))
    {
        $language_to_set=returnGlobal('lang',true);
    }
    elseif (isset($oTokenEntry) && $oTokenEntry)
    {
        // If survey have token : we have a $oTokenEntry
        // Can use $oTokenEntry = Token::model($surveyid)->findByAttributes(array('token'=>$clienttoken)); if we move on another function : this par don't validate the token validity
        $language_to_set=$oTokenEntry->language;
    }
    else
    {
        $language_to_set = $thissurvey['language'];
    }


    if (App()->surveySessionManager->current->survey->questionCount == 0) {
        throw new \Exception("There are no questions in this survey");
    }
    //Perform a case insensitive natural sort on group name then question title of a multidimensional array
    //    usort($arows, 'groupOrderThenQuestionOrder');

    //3. SESSION VARIABLE - insertarray
    //An array containing information about used to insert the data into the db at the submit stage
    //4. SESSION VARIABLE - fieldarray
    //See rem at end..

    if ($tokensexist == 1 && $clienttoken)
    {
        $_SESSION['survey_'.$surveyid]['token'] = $clienttoken;
    }

    if ($thissurvey['anonymized'] == "N")
    {
        $_SESSION['survey_'.$surveyid]['insertarray'][]= "token";
    }

    $qtypes=getQuestionTypeList('','array');
    $fieldmap = createFieldMap($surveyid,'full',true,false,$session->language);

    //Check if a passthru label and value have been included in the query url
    $oResult=SurveyURLParameter::model()->getParametersForSurvey($surveyid);
    foreach($oResult->readAll() as $aRow)
    {
        if(isset($_GET[$aRow['parameter']]) && !$preview)
        {
            $_SESSION['survey_'.$surveyid]['urlparams'][$aRow['parameter']]=$_GET[$aRow['parameter']];
            if ($aRow['targetqid']!='')
            {
                foreach ($fieldmap as $sFieldname=>$aField)
                {
                    if ($aRow['targetsqid']!='')
                    {
                        if ($aField['qid']==$aRow['targetqid'] && $aField['sqid']==$aRow['targetsqid'])
                        {
                            $_SESSION['survey_'.$surveyid]['startingValues'][$sFieldname]=$_GET[$aRow['parameter']];
                            $_SESSION['survey_'.$surveyid]['startingValues'][$aRow['parameter']]=$_GET[$aRow['parameter']];
                        }
                    }
                    else
                    {
                        if ($aField['qid']==$aRow['targetqid'])
                        {
                            $_SESSION['survey_'.$surveyid]['startingValues'][$sFieldname]=$_GET[$aRow['parameter']];
                            $_SESSION['survey_'.$surveyid]['startingValues'][$aRow['parameter']]=$_GET[$aRow['parameter']];
                        }
                    }
                }

            }
        }
    }
    eP();

}

/**
* This function creates the form elements in the survey navigation bar
* Adding a hidden input for default behaviour without javascript
* Use button name="move" for real browser (with or without javascript) and IE6/7/8 with javascript
*/
function surveymover()
{
    $session = App()->surveySessionManager->current;
    $surveyid = $session->surveyId;

    $sMoveNext="movenext";
    $sMovePrev="";
    $iSessionStep = $session->step;
    $iSessionMaxStep= $session->maxStep;
    $iSessionTotalSteps= $session->stepCount;
    $sClass="submit button";
    $sSurveyMover = "";

    // Count down
    if ($session->survey->navigationdelay > 0 && $iSessionMaxStep == $iSessionStep)
     {
        $sClass.=" disabled";
        App()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."/navigator-countdown.js");
        App()->getClientScript()->registerScript('navigator_countdown',"navigator_countdown({$session->survey->navigationdelay});\n",CClientScript::POS_BEGIN);
     }

    // Previous ?
    if ($session->format != Survey::FORMAT_ALL_IN_ONE && $session->survey->bool_allowprev){
        $sMovePrev="moveprev";
    }

    // Submit ?
    if ($iSessionStep == $iSessionTotalSteps
        || $session->format == Survey::FORMAT_ALL_IN_ONE
    ){
        $sMoveNext="movesubmit";
    }

    // Construction of mover
    if($sMovePrev){
        $sLangMoveprev=gT("Previous");
        $sSurveyMover.= CHtml::htmlButton($sLangMoveprev,array('type'=>'submit','id'=>"{$sMovePrev}btn",'value'=>$sMovePrev,'name'=>$sMovePrev,'accesskey'=>'p','class'=>$sClass));
    }
    if($sMovePrev && $sMoveNext){
        $sSurveyMover .= " ";
    }

    if($sMoveNext){
        if($sMoveNext=="movesubmit"){
            $sLangMovenext=gT("Submit");
            $sAccessKeyNext='l';// Why l ?
        }else{
            $sLangMovenext=gT("Next");
            $sAccessKeyNext='n';
        }
        $sSurveyMover.= CHtml::htmlButton($sLangMovenext,array('type'=>'submit','id'=>"{$sMoveNext}btn",'value'=>$sMoveNext,'name'=>$sMoveNext,'accesskey'=>$sAccessKeyNext,'class'=>$sClass));
     }
    return $sSurveyMover;
}

/**
* Caculate assessement scores
*
* @param mixed $surveyid
* @param mixed $returndataonly - only returns an array with data
*/
function doAssessment(Survey $survey, $returndataonly=false)
{
    $session = App()->surveySessionManager->current;


    if(!isset($session) || !$session->survey->bool_assessments) {
        return false;
    }
    $total = 0;
    $query = "SELECT * FROM {{assessments}}
    WHERE sid={$session->surveyId} and language='{$session->language}'
    ORDER BY scope, id";

    if ($result = dbExecuteAssoc($query))   //Checked
    {
        $aResultSet=$result->readAll();
        if (count($aResultSet) > 0)
        {
            foreach($aResultSet as $row)
            {
                if ($row['scope'] == "G")
                {
                    $assessment['group'][$row['gid']][]=array("name"=>$row['name'],
                    "min"=>$row['minimum'],
                    "max"=>$row['maximum'],
                    "message"=>$row['message']);
                }
                else
                {
                    $assessment['total'][]=array( "name"=>$row['name'],
                    "min"=>$row['minimum'],
                    "max"=>$row['maximum'],
                    "message"=>$row['message']);
                }
            }
            $fieldmap=createFieldMap($surveyid, "full",false,false,$session->language);
            $i=0;
            $total=0;
            $groups=array();
            foreach($fieldmap as $field)
            {
                if (in_array($field['type'],array('1','F','H','W','Z','L','!','M','O','P')))
                {
                    $fieldmap[$field['fieldname']]['assessment_value']=0;
                    if (isset($_SESSION['survey_'.$surveyid][$field['fieldname']]))
                    {
                        if (($field['type'] == "M") || ($field['type'] == "P")) //Multiflexi choice  - result is the assessment attribute value
                        {
                            if ($_SESSION['survey_'.$surveyid][$field['fieldname']] == "Y")
                            {
                                $aAttributes=\QuestionAttribute::model()->getQuestionAttributes($field['qid'],$field['type']);
                                $fieldmap[$field['fieldname']]['assessment_value']=(int)$aAttributes['assessment_value'];
                                $total=$total+(int)$aAttributes['assessment_value'];
                            }
                        }
                        else  // Single choice question
                        {
                            $usquery = "SELECT assessment_value FROM {{answers}} where qid=".$field['qid']." and language='$baselang' and code=".App()->db->quoteValue($_SESSION['survey_'.$surveyid][$field['fieldname']]);
                            $usresult = dbExecuteAssoc($usquery);          //Checked
                            if ($usresult)
                            {
                                $usrow = $usresult->read();
                                $fieldmap[$field['fieldname']]['assessment_value']=$usrow['assessment_value'];
                                $total=$total+$usrow['assessment_value'];
                            }
                        }
                    }
                    $groups[]=$field['gid'];
                }
                $i++;
            }

            $groups=array_unique($groups);

            foreach($groups as $group)
            {
                $grouptotal=0;
                foreach ($fieldmap as $field)
                {
                    if ($field['gid'] == $group && isset($field['assessment_value']))
                    {
                        //$grouptotal=$grouptotal+$field['answer'];
                        if (isset ($_SESSION['survey_'.$surveyid][$field['fieldname']]))
                        {
                            $grouptotal=$grouptotal+$field['assessment_value'];
                        }
                    }
                }
                $subtotal[$group]=$grouptotal;
            }
        }
        $assessments = "";
        if (isset($subtotal) && is_array($subtotal))
        {
            foreach($subtotal as $key=>$val)
            {
                if (isset($assessment['group'][$key]))
                {
                    foreach($assessment['group'][$key] as $assessed)
                    {
                        if ($val >= $assessed['min'] && $val <= $assessed['max'] && $returndataonly===false)
                        {
                            $assessments .= "\t<!-- GROUP ASSESSMENT: Score: $val Min: ".$assessed['min']." Max: ".$assessed['max']."-->
                            <table class='assessments'>
                            <tr>
                            <th>".str_replace(array("{PERC}", "{TOTAL}"), array($val, $total), $assessed['name'])."
                            </th>
                            </tr>
                            <tr>
                            <td>".str_replace(array("{PERC}", "{TOTAL}"), array($val, $total), $assessed['message'])."
                            </td>
                            </tr>
                            </table><br />\n";
                        }
                    }
                }
            }
        }

        if (isset($assessment['total']))
        {
            foreach($assessment['total'] as $assessed)
            {
                if ($total >= $assessed['min'] && $total <= $assessed['max'] && $returndataonly===false)
                {
                    $assessments .= "\t\t\t<!-- TOTAL ASSESSMENT: Score: $total Min: ".$assessed['min']." Max: ".$assessed['max']."-->
                    <table class='assessments' align='center'>
                    <tr>
                    <th>".str_replace(array("{PERC}", "{TOTAL}"), array($val, $total), stripslashes($assessed['name']))."
                    </th>
                    </tr>
                    <tr>
                    <td>".str_replace(array("{PERC}", "{TOTAL}"), array($val, $total), stripslashes($assessed['message']))."
                    </td>
                    </tr>
                    </table>\n";
                }
            }
        }
        if ($returndataonly==true)
        {
            return array('total'=>$total);
        }
        else
        {
            return $assessments;
        }
    }
}



/**
* checkCompletedQuota() returns matched quotas information for the current response
* @param integer $surveyid - Survey identification number
* @param bool $return - set to true to return information, false do the quota
* @return array - nested array, Quotas->Members->Fields, includes quota information matched in session.
*/
function checkCompletedQuota($return=false)
{
    bP();
    static $aMatchedQuotas; // EM call 2 times quotas with 3 lines of php code, then use static.
    $session = App()->surveySessionManager->current;
    if(!$aMatchedQuotas)
    {
        $aMatchedQuotas=array();
        $quota_info=$aQuotasInfo = getQuotaInformation($session->surveyId, $session->language);
        // $aQuotasInfo have an 'active' key, we don't use it ?
        if(!$aQuotasInfo || empty($aQuotasInfo))
            return $aMatchedQuotas;
        // OK, we have some quota, then find if this $_SESSION have some set
        $aPostedFields = explode("|",Yii::app()->request->getPost('fieldnames','')); // Needed for quota allowing update 
        foreach ($aQuotasInfo as $aQuotaInfo)
        {
            $iMatchedAnswers=0;
            $bPostedField=false;
            // Array of field with quota array value
            $aQuotaFields=array();
            // Array of fieldnames with relevance value : EM fill $_SESSION with default value even is unrelevant (em_manager_helper line 6548)
            $aQuotaRelevantFieldnames=array();
            foreach ($aQuotaInfo['members'] as $aQuotaMember)
            {
                $aQuotaFields[$aQuotaMember['fieldname']][] = $aQuotaMember['value'];
                $aQuotaRelevantFieldnames[$aQuotaMember['fieldname']]=isset($_SESSION['survey_'.$session->surveyId]['relevanceStatus'][$aQuotaMember['qid']]) && $_SESSION['survey_'.$session->surveyId]['relevanceStatus'][$aQuotaMember['qid']];
            }
            // For each field : test if actual responses is in quota (and is relevant)
            foreach ($aQuotaFields as $sFieldName=>$aValues)
            {
                $bInQuota=isset($_SESSION['survey_'.$session->surveyId][$sFieldName]) && in_array($_SESSION['survey_'.$session->surveyId][$sFieldName],$aValues);
                if($bInQuota && $aQuotaRelevantFieldnames[$sFieldName])
                {
                    $iMatchedAnswers++;
                }
                if(in_array($sFieldName,$aPostedFields))// Need only one posted value
                    $bPostedField=true;
            }
            // Count only needed quotas
            if($iMatchedAnswers==count($aQuotaFields) && ( $aQuotaInfo['action']!=2 || $bPostedField ) )
            {
                if($aQuotaInfo['qlimit'] == 0){ // Always add the quota if qlimit==0
                    $aMatchedQuotas[]=$aQuotaInfo;
                }else{
                    $iCompleted=getQuotaCompletedCount($session->surveyId, $aQuotaInfo['id']);
                    if(!is_null($iCompleted) && ((int)$iCompleted >= (int)$aQuotaInfo['qlimit'])) // This remove invalid quota and not completed
                        $aMatchedQuotas[]=$aQuotaInfo;
                }
            }
        }
    }
    if ($return)
        return $aMatchedQuotas;
    if(empty($aMatchedQuotas))
        return;

    // Now we have all the information we need about the quotas and their status.
    // We need to construct the page and do all needed action
    $aSurveyInfo=getSurveyInfo($session->surveyId, $session->language);
    $sTemplatePath=Template::getTemplatePath($aSurveyInfo['template']);
    $sClientToken=isset($_SESSION['survey_'.$session->surveyId]['token'])?$_SESSION['survey_'.$session->surveyId]['token']:"";
    // $redata for templatereplace
    $aDataReplacement = array(
        'thissurvey'=>$aSurveyInfo,
        'clienttoken'=>$sClientToken,
        'token'=>$sClientToken,
    );

    // We take only the first matched quota, no need for each
    $aMatchedQuota=$aMatchedQuotas[0];
    // If a token is used then mark the token as completed, do it before event : this allow plugin to update token information
    $event = new PluginEvent('afterSurveyQuota');
    $event->set('surveyId', $session->surveyId);
    $event->set('responseId', $_SESSION['survey_'.$session->surveyId]['srid']);// We allways have a responseId
    $event->set('aMatchedQuotas', $aMatchedQuotas);// Give all the matched quota : the first is the active
    App()->getPluginManager()->dispatchEvent($event);
    $blocks = array();
    foreach ($event->getAllContent() as $blockData)
    {
        /* @var $blockData PluginEventContent */
        $blocks[] = CHtml::tag('div', array('id' => $blockData->getCssId(), 'class' => $blockData->getCssClass()), $blockData->getContent());
    }
    // Allow plugin to update message, url, url description and action
    $sMessage=$event->get('message',$aMatchedQuota['quotals_message']);
    $sUrl=$event->get('url',$aMatchedQuota['quotals_url']);
    $sUrlDescription=$event->get('urldescrip',$aMatchedQuota['quotals_urldescrip']);
    $sAction=$event->get('action',$aMatchedQuota['action']);
    $sAutoloadUrl=$event->get('autoloadurl',$aMatchedQuota['autoload_url']);

    // Doing the action and show the page
    if ($sAction == "1" && $sClientToken)
        submittokens(true);
    // Construct the default message
    $sMessage = templatereplace($sMessage, array(), $aDataReplacement, 'QuotaMessage', null, true);
    $sUrl = passthruReplace($sUrl, $aSurveyInfo);
    $sUrl = templatereplace($sUrl, array(), $aDataReplacement, 'QuotaUrl', null, true);
    $sUrlDescription = templatereplace($sUrlDescription, array(), $aDataReplacement, 'QuotaUrldescription', null, true);

    // Construction of default message inside quotamessage class
    $sHtmlQuotaMessage = "<div class='quotamessage limesurveycore'>\n";
    $sHtmlQuotaMessage.= "\t".$sMessage."\n";
    $sHtmlQuotaUrl=($sUrl)? "<a href='".$sUrl."'>".$sUrlDescription."</a>" : "";

    // Add the navigator with Previous button if quota allow modification.
    if ($sAction == "2")
    {
        $sQuotaStep= App()->surveySessionManager->current->getStep(); // Surely not needed
        $sNavigator = CHtml::htmlButton(gT("Previous"),array('type'=>'submit','id'=>"moveprevbtn",'value'=>$sQuotaStep,'name'=>'move','accesskey'=>'p','class'=>"submit button"));
        //$sNavigator .= " ".CHtml::htmlButton(gT("Submit"),array('type'=>'submit','id'=>"movesubmit",'value'=>"movesubmit",'name'=>"movesubmit",'accesskey'=>'l','class'=>"submit button"));
        $sHtmlQuotaMessage.= CHtml::form(array("/survey/index","sid"=>$session->surveyId), 'post', array('id'=>'limesurvey','name'=>'limesurvey'));
        $sHtmlQuotaMessage.=renderOldTemplate($sTemplatePath . "/navigator.pstpl",
            array('NAVIGATOR' => $sNavigator, 'SAVE' => ''), $aDataReplacement);
        $sHtmlQuotaMessage.= CHtml::hiddenField('sid',$session->surveyId);
        $sHtmlQuotaMessage.= CHtml::hiddenField('token',$sClientToken);// Did we really need it ?
        $sHtmlQuotaMessage.= CHtml::endForm();
    }
    $sHtmlQuotaMessage.= "</div>\n";
    // Add the plugin message before default message
    $sHtmlQuotaMessage = implode("\n", $blocks) ."\n". $sHtmlQuotaMessage;

    // Send page to user and end.
    sendCacheHeaders();
    if($sAutoloadUrl == 1 && $sUrl != "")
    {
        if ($sAction == "1")
            killSurveySession($session->surveyId);
        header("Location: ".$sUrl);
    }
    doHeader();
    renderOldTemplate($sTemplatePath . "/startpage.pstpl", array(), $aDataReplacement);
    renderOldTemplate($sTemplatePath . "/completed.pstpl",
        array("COMPLETED" => $sHtmlQuotaMessage, "URL" => $sHtmlQuotaUrl), $aDataReplacement);
    renderOldTemplate($sTemplatePath . "/endpage.pstpl", array(), $aDataReplacement);
    doFooter();
    if ($sAction == "1")
        killSurveySession($surveyid);

    eP();
    Yii::app()->end();
}

/**
* encodeEmail : encode admin email in public part
*
* @param mixed $mail
* @param mixed $text
*/
function encodeEmail($mail, $text="")
{
    $encmail ="";
    for($i=0; $i<strlen($mail); $i++)
    {
        $encMod = rand(0,2);
        switch ($encMod)
        {
            case 0: // None
                $encmail .= substr($mail,$i,1);
                break;
            case 1: // Decimal
                $encmail .= "&#".ord(substr($mail,$i,1)).';';
                break;
            case 2: // Hexadecimal
                $encmail .= "&#x".dechex(ord(substr($mail,$i,1))).';';
                break;
        }
    }

    if(!$text)
    {
        $text = $encmail;
    }
    return $text;
}

/**
* GetReferringUrl() returns the referring URL
* @return string
*/
function GetReferringUrl()
{
    // read it from server variable
    if(isset($_SERVER["HTTP_REFERER"]))
    {
        if (!Yii::app()->getConfig('strip_query_from_referer_url'))
        {
            return $_SERVER["HTTP_REFERER"];
        }
        else
        {
            $aRefurl = explode("?",$_SERVER["HTTP_REFERER"]);
            return $aRefurl[0];
        }
    }
    else
    {
        return null;
    }
}

/**
* Shows the welcome page, used in group by group and question by question mode
*/
function display_first_page() {
    global $token, $surveyid, $thissurvey, $navigator;
    $totalquestions = $_SESSION['survey_'.$surveyid]['totalquestions'];

    // Fill some necessary var for template
    $navigator = surveymover();
    $sitename = App()->name;
    $languagechanger=makeLanguageChangerSurvey(App()->language);

    sendCacheHeaders();
    doHeader();

    LimeExpressionManager::StartProcessingPage();
    LimeExpressionManager::StartProcessingGroup(-1, false);  // start on welcome page

    $redata = compact(array_keys(get_defined_vars()));
    $sTemplatePath=$_SESSION['survey_'.$surveyid]['templatepath'];

    renderOldTemplate($sTemplatePath . "startpage.pstpl", array(), $redata,
        'frontend_helper[2757]');
    echo CHtml::form(array("/survey/index","sid"=>$surveyid), 'post', array('id'=>'limesurvey','name'=>'limesurvey','autocomplete'=>'off'));
    echo "\n\n<!-- START THE SURVEY -->\n";

    renderOldTemplate($sTemplatePath . "welcome.pstpl", array(), $redata, 'frontend_helper[2762]')."\n";
    if ($thissurvey['anonymized'] == "Y")
    {
        renderOldTemplate($sTemplatePath . "/privacy.pstpl", array(), $redata,
                'frontend_helper[2765]')."\n";
    }
    renderOldTemplate($sTemplatePath . "navigator.pstpl", array(), $redata,
        'frontend_helper[2767]');
    if ($thissurvey['active'] != "Y")
    {
        echo "<p style='text-align:center' class='error'>".gT("This survey is currently not active. You will not be able to save your responses.")."</p>\n";
    }
    echo "\n<input type='hidden' name='sid' value='$surveyid' id='sid' />\n";
    if (isset($token) && !empty($token)) {
        echo "\n<input type='hidden' name='token' value='$token' id='token' />\n";
    }
    echo "\n<input type='hidden' name='lastgroupname' value='_WELCOME_SCREEN_' id='lastgroupname' />\n"; //This is to ensure consistency with mandatory checks, and new group test
    $loadsecurity = returnGlobal('loadsecurity',true);
    if (isset($loadsecurity)) {
        echo "\n<input type='hidden' name='loadsecurity' value='$loadsecurity' id='loadsecurity' />\n";
    }
    $session = App()->surveySessionManager->current;
    echo "<input type='hidden' name='LEMpostKey' value='{$session->postKey}' id='LEMpostKey' />\n";
    echo "<input type='hidden' name='thisstep' id='thisstep' value='0' />\n";

    echo "\n</form>\n";
    renderOldTemplate($sTemplatePath . "endpage.pstpl", array(), $redata, 'frontend_helper[2782]');

    echo LimeExpressionManager::GetRelevanceAndTailoringJavaScript();
    LimeExpressionManager::FinishProcessingPage();
    doFooter();
}

/**
* killSurveySession : reset $_SESSION part for the survey
* @param int $iSurveyID
*/
function killSurveySession($iSurveyID)
{
    // Unset the session
    unset($_SESSION['survey_'.$iSurveyID]);
    // Force EM to refresh
    LimeExpressionManager::SetDirtyFlag();
}

/**
* Resets all question timers by expiring the related cookie - this needs to be called before any output is done
* @todo Make cookie survey ID aware
*/
function resetTimers()
{
    $cookie=new CHttpCookie('limesurvey_timers', '');
    $cookie->expire = time()- 3600;
    Yii::app()->request->cookies['limesurvey_timers'] = $cookie;
}

/**
* Set the public survey language
* Control if language exist in this survey, else set to survey default language
* if $surveyid <= 0 : set the language to default site language
* @param int $surveyid
* @param string $language
*/
function SetSurveyLanguage($surveyid, $sLanguage)
{
    $session = App()->surveySessionManager->current;
    $surveyid=sanitize_int($surveyid);
    $default_language = SettingGlobal::get('defaultlang');

    if (isset($surveyid) && $surveyid>0)
    {
        $default_survey_language= Survey::model()->cache(1)->findByPk($surveyid)->language;
        $additional_survey_languages = Survey::model()->findByPk($surveyid)->getAdditionalLanguages();
        if (!isset($sLanguage) || ($sLanguage=='')
        || !( in_array($sLanguage,$additional_survey_languages) || $sLanguage==$default_survey_language)
        )
        {
            // Language not supported, fall back to survey's default language
            $session->language = $default_survey_language;
        } else {
            $session->language =  $sLanguage;
        }
        App()->setLanguage($session->language);
        Yii::app()->loadHelper('surveytranslator');
    }
    else
    {
        if(!$sLanguage)
        {
            $sLanguage=$default_language;
        }
        $session->language = $sLanguage;
        App()->setLanguage($session->language);
    }

}

/**
* getMove get move button clicked
**/
function getMove()
{
#
    $aAcceptedMove=array('default','movenext','movesubmit','moveprev','saveall','loadall','clearall','changelang');
    // We can control is save and load are OK : todo fix according to survey settings
    // Maybe allow $aAcceptedMove in Plugin
    $move=Yii::app()->request->getParam('move');
    foreach($aAcceptedMove as $sAccepteMove)
    {
        if(Yii::app()->request->getParam($sAccepteMove))
            $move=$sAccepteMove;
    }
    if($move=='default')
    {
        $session = App()->surveySessionManager->current;
        $surveyid = $session->surveyId;
        $thissurvey = getsurveyinfo($surveyid);
        $iSessionStep = $session->step;
        $iSessionTotalSteps = $session->totalSteps;
        if ($iSessionStep && ($iSessionStep == $iSessionTotalSteps)|| $thissurvey['format'] == 'A')
        {
            $move="movesubmit";
        }
        else
        {
            $move="movenext";
        }
    }
    return $move;
}

