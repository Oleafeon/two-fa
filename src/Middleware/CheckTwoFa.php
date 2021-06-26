<?php

namespace KindWork\TwoFa\Middleware;

use Config;
use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\User;

class CheckTwoFa
{
  protected $user;
  protected $request;

  public function handle(Request $request, Closure $next)
  {
    $this->request = $request;

    if ($this->isAuthed()) {
      if ($this->isTwoFAPath() && !$request->ajax()) {
        return redirect(cp_route('index'));
      }
      return $next($this->request);
    }

    $this->user = User::current()->data();

    if (!$this->isTwoFAPath() && $this->isCodeRequired()) {
      // If we get here go to the two FA page to get the code
      return redirect(cp_route('two-fa'));
    }

    if (!$this->isTwoFAPath() && $this->isSetupForced()) {
      return redirect(cp_route('two-fa.setup'));
    }

    // Otherwise lets continue
    return $next($this->request);
  }

  private function isAuthed()
  {
    return // Check to see if we already authed with 2FA on the session
      $this->request->session()->get('two_fa_authenticated');
  }

  private function isCodeRequired()
  {
    return $this->isEnabled() &&
      // Make sure we are not already authed with 2FA on the session
      !$this->isAuthed();
  }

  private function isEnabled()
  {
    return // Make sure we have a user
      $this->user &&
        // Make sure two_fa is set
        isset($this->user['two_fa']) &&
        // Make sure we have a key
        !empty($this->user['two_fa']);
  }

  private function isTwoFAPath()
  {
    return $this->request->path() === 'cp/two-fa' ||
      $this->request->path() === 'cp/two-fa/setup' ||
      $this->request->path() === 'cp/two-fa/activate-two-fa';
  }

  private function isSetupForced()
  {
    $force = Config::get('two-fa.force');

    // If 2FA Forced for all users
    if ($force === true) {
      return true;
    }

    // If Forced for the current user role
    if ($force == 'roles-only' && $this->rolesIntersect()) {
      return true;
    }

    // If Forced Exception List does not contain the current role
    if ($force == 'roles-except' && !$this->rolesIntersect()) {
      return true;
    }

    // Otherwise it is not forced
    return false;
  }

  private function rolesIntersect()
  {
    $roles = Config::get('two-fa.roles');
    return (in_array('super', $roles) && $this->user['super'] === true) ||
      array_intersect($roles, $this->user['roles']);
  }
}
