<?php

declare(strict_types=1);
namespace App\Enums;
enum ModerationActionType: string { case WARNING='warning'; case HIDE_REVIEW='hide_review'; case UNPUBLISH_SERVICE='unpublish_service'; case SUSPEND_USER='suspend_user'; case SUSPEND_PROFESSIONAL='suspend_professional'; }
