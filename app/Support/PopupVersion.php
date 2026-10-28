<?php
namespace App\Support;

use App\Models\LoginPopupSetting;

final class PopupVersion
{
    public static function token(?LoginPopupSetting $setting): string
    {
        return hash('sha256', json_encode([$setting?->enabled ?? false, $setting?->title, $setting?->message]));
    }
}
