<?php

namespace App\Http\Controllers;

use App\Helpers\MenuHelper;

class SidebarController extends Controller
{
    public function getMenuData()
    {
        $menuGroups = MenuHelper::getMenuGroups();

        return view('components.sidebar', compact('menuGroups'));
    }
}
