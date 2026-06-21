<?php
declare(strict_types=1);

namespace EG_MEDIA\Enums;

/**
 * Énumération des statuts des médias pour EG Media Manager.
 *
 * @package EG_MEDIA\Enums
 */
enum Media_Status: string {
	case PENDING  = 'pending';
	case APPROVED = 'approved';
	case REJECTED = 'rejected';
}
