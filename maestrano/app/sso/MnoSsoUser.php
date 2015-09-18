<?php

/**
 * Configure App specific behavior for Maestrano SSO
 */
class MnoSsoUser extends Maestrano_Sso_User {
  /**
   * Extend constructor to inialize app specific objects
   *
   * @param OneLogin_Saml_Response $saml_response
   *   A SamlResponse object from Maestrano containing details
   *   about the user being authenticated
   */
  public function __construct($saml_response) {
    parent::__construct($saml_response);
  }
  
  /**
  * Find or Create a user based on the SAML response parameter and Add the user to current session
  */
  public function findOrCreate() {
    // Find user by uid. Is it exists, it has already signed in using SSO
    $local_id = $this->getLocalIdByUid();
    $new_user = ($local_id == null);
    // Find user by email
    if($local_id == null) { $local_id = $this->getLocalIdByEmail(); }

    if ($local_id) {
      // User found, load it
      $this->local_id = $local_id;
      $this->syncLocalDetails($new_user);
    } else {
      // New user, create it
      $this->local_id = $this->createLocalUser();
      $this->setLocalUid();
    }

    // Add user to current session
    $this->setInSession();
  }
  
  /**
   * Sign the user in the application. 
   * Parent method deals with putting the mno_uid, 
   * mno_session and mno_session_recheck in session.
   *
   * @return boolean whether the user was successfully set in session or not
   */
  protected function setInSession() {
    $user = User::model()->find('uid=:uid', array(':uid'=>$this->local_id));
    
    if ($user) {
      $identity = new UserIdentity($this->uid, '');
      $identity->authenticate('',true);
      Yii::app()->user->login($identity);
      
      Yii::app()->session['loginID'] = (int) $user->uid;
      Yii::app()->session['user'] = $user->users_name;
      Yii::app()->session['full_name'] = $user->full_name;
      Yii::app()->session['htmleditormode'] = $user->htmleditormode;
      Yii::app()->session['templateeditormode'] = $user->templateeditormode;
      Yii::app()->session['questionselectormode'] = $user->questionselectormode;
      Yii::app()->session['dateformat'] = $user->dateformat;
      Yii::app()->session['session_hash'] = hash('sha256', getGlobalSetting('SessionName').$user->users_name.$user->uid);
      
      return true;
    } else {
        return false;
    }
  }
  
  
  /**
   * Used by createLocalUserOrDenyAccess to create a local user 
   * based on the sso user.
   * If the method returns null then access is denied
   *
   * @return the ID of the user created, null otherwise
   */
  protected function createLocalUser() {          
    // Build user and save it
    $user = $this->buildLocalUser();
    $user->save();

    $permission=new Permission;
    $permission->entity_id=0;
    $permission->entity='global';
    $permission->uid=$user->uid;
    $permission->permission='superadmin';
    $permission->read_p=1;
    $permission->save();
          
    return $user->uid;
  }
  
  /**
   * Used by createLocalUserOrDenyAccess to create a local user 
   * based on the sso user.
   * If the method returns null then access is denied
   *
   * @return the ID of the user created, null otherwise
   */
  protected function buildLocalUser() {
    $is_admin = $this->getRoleIdToAssign();
    
    $user = new User;
    $user->users_name = $this->uid;
    $user->full_name = "$this->getFirstName() $this->getLastName()";
    $user->email = $this->getEmail();
    $user->lang = 'auto';
    $user->password = $this->generatePassword();
    
    return $user;
  }
  
  /**
   * Return 1 if admin 0 otherwise
   *
   * @return 1 or 0 (integer boolean flag )
   */
  public function getRoleIdToAssign() {
    switch($this->getGroupRole()) {
      case 'Member':
        return 0;
      case 'Power User':
        return 0;
      case 'Admin':
        return 1;
      case 'Super Admin':
        return 1;
      default:
        return 0;
    }
  }
  
  /**
   * Get the ID of a local user via Maestrano UID lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByUid() {
    $user = User::model()->find('mno_uid=:mno_uid', array(':mno_uid'=>$this->uid));
    
    if ($user) {
      return $user->uid;
    }
    
    return null;
  }
  
  /**
   * Get the ID of a local user via email lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByEmail() {
    $user = User::model()->find('email=:email', array(':email'=>$this->getEmail()));
    
    if ($user) {
      return $user->uid;
    }
    
    return null;
  }
  
  /**
   * Set all 'soft' details on the user (like name, surname, email)
   * Implementing this method is optional.
   *
   * @return boolean whether the user was synced or not
   */
   protected function syncLocalDetails() {
     if($this->local_id) {
       $user = User::model()->find('uid=:uid', array(':uid'=>$this->local_id));
       $user->users_name = $this->uid;
       $user->full_name = "$this->getFirstName() $this->getLastName()";
       $user->email = $this->getEmail();
       return $user->save();
     }
     
     return false;
   }
  
  /**
   * Set the Maestrano UID on a local user via id lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function setLocalUid() {
    if($this->local_id) {
      $user = User::model()->find('uid=:uid', array(':uid'=>$this->local_id));
      $user->mno_uid = $this->uid;
      return $user->save();
    }
    
    return false;
  }

  /**
  * Generate a random password.
  * Convenient to set dummy passwords on users
  *
  * @return string a random password
  */
  protected function generatePassword() {
    $length = 20;
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
  }
}