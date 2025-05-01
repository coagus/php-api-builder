<?php
namespace DemoApi;

use ApiBuilder\ORM\APIDB;
use ApiBuilder\Auth;

class User extends APIDB
{
  public function postFirstUser()
  {
    $usr = $this->getFilledEntity(true);

    if (count($usr->getAll()['data']) != 0)
      error('There are users in the database for this request.', SC_ERROR_UNAUTHORIZED);

    $usr->password = password_hash($usr->password, PASSWORD_DEFAULT);
    $usr->roleId = 1;
    $usr->save();

    success('User created successfully.');
  }

  public function postActivateUser()
  {
    $usr = $this->getFilledEntity(true);

    $pwd = $usr->password;
    $usr->password = null;

    $usrFound = $usr->getWhere()['data'];

    if (empty($usrFound))
      error('User not exists.', SC_ERROR_UNAUTHORIZED);

    if ($usrFound[0]->active == 1)
      error('User is already active.', SC_ERROR_NOT_FOUND);

    if (!password_verify($pwd, $usrFound[0]->password))
      error('Invalid password.', SC_ERROR_UNAUTHORIZED);

    debug("usr", $usr);

    $usr->id = $usrFound[0]->id;
    $usr->active = 1;
    $usr->save();
    success('User activated successfully.');
  }

  public function postLogin()
  {
    $usr = $this->getFilledEntity(true);
    $pwd = $usr->password;
    $usr->password = null;

    $usrFound = $usr->getWhere()['data'];

    if (empty($usrFound))
      error('User not exists.', SC_ERROR_UNAUTHORIZED);

    if ($usrFound[0]->active == 0)
      error('User is not active.', SC_ERROR_UNAUTHORIZED);

    if (!password_verify($pwd, $usrFound[0]->password))
      error('Invalid password.', SC_ERROR_UNAUTHORIZED);

    unset($usrFound[0]->password);
    unset($usrFound[0]->active);

    $auth = new Auth();
    $token = $auth->getToken($usrFound[0]);

    success(['user' => $usrFound[0], 'token' => $token]);
  }
}