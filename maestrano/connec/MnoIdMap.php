<?php

class MnoIdMap {
  public static function addMnoIdMap($local_id, $local_entity_name, $mno_id, $mno_entity_name) {
    $query = "INSERT INTO mno_id_map (mno_entity_guid, mno_entity_name, app_entity_id, app_entity_name, db_timestamp) VALUES ('".$mno_id."','".strtoupper($mno_entity_name)."','".$local_id."','".strtoupper($local_entity_name)."', LOCALTIMESTAMP(0))";
    
    $result = Yii::app()->db->createCommand($query)->query();
    return $result;
  }

  public static function findMnoIdMapByMnoIdAndEntityName($mno_id, $mno_entity_name, $local_entity_name=null) {
    $query = '';
    if(is_null($local_entity_name)) {
      $query = "SELECT * from mno_id_map WHERE mno_entity_guid = '$mno_id' AND mno_entity_name = '".strtoupper($mno_entity_name)."' ORDER BY deleted_flag LIMIT 1";
    } else {
      $query = "SELECT * from mno_id_map WHERE mno_entity_guid = '$mno_id' AND mno_entity_name = '".strtoupper($mno_entity_name)."' AND app_entity_name = '".strtoupper($local_entity_name)."' ORDER BY deleted_flag LIMIT 1";
    }
    
    $result = Yii::app()->db->createCommand($query)->queryRow();
    if(count($result) > 0) { return $result; }
    return null;
  }

  public static function findMnoIdMapByLocalIdAndEntityName($local_id, $local_entity_name) {
    $query = "SELECT * from mno_id_map WHERE app_entity_id = '".$local_id."' AND app_entity_name = '".strtoupper($local_entity_name)."' ORDER BY deleted_flag";
    
    $result = Yii::app()->db->createCommand($query)->queryRow();
    if(count($result) > 0) { return $result; }
    return null;
  }

  public static function deleteMnoIdMap($local_id, $local_entity_name) {
    $query = "UPDATE mno_id_map SET deleted_flag = 1 WHERE app_entity_id = '".$local_id."' AND app_entity_name = '".strtoupper($local_entity_name)."'";
    return Yii::app()->db->createCommand($query)->query();
  }

  public static function hardDeleteMnoIdMap($local_id, $local_entity_name) {
    $query = "DELETE FROM mno_id_map WHERE app_entity_id = '".$local_id."' AND app_entity_name = '".strtoupper($local_entity_name)."'";
    return Yii::app()->db->createCommand($query)->query();
  }

  public static function updateIdMapEntry($current_mno_id, $new_mno_id, $mno_entity_name) {
    $query = "UPDATE mno_id_map SET mno_entity_guid = '".$new_mno_id."' WHERE mno_entity_guid = '".$current_mno_id."' AND mno_entity_name = '".strtoupper($mno_entity_name)."'";
    return Yii::app()->db->createCommand($query)->query();
  }
}