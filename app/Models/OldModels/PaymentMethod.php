<?php

namespace App\Models\OldModels;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PaymentMethod
 *
 * @property int $id
 * @property string $name
 *
 * @property Collection|Bookings2[] $bookings
 *
 * @package App\Models
 */
class PaymentMethod extends Model
{
	protected $table = 'payment_methods';
	public $timestamps = false;

	protected $connection = 'old';

protected $fillable = [
		'name'
	];


    /**
     * Relations
     */

	public function bookings()
	{
		return $this->hasMany(Bookings2::class);
	}


    // Constant PaymentMethod IDs as of 2022-1021:
    /** PaymentMethod for 'Cash' */
    const ID_CASH = 1;
    /** PaymentMethod for 'BoukiiPay' (i.e. credit card now) */
    const ID_BOUKIIPAY = 2;
    /** PaymentMethod for 'Online' (i.e. credit card via email) */
    const ID_ONLINE = 3;
    /** PaymentMethod for 'Other' */
    const ID_OTHER = 4;
    /** PaymentMethod for 'No payment' */
    const ID_NOPAYMENT = 5;
    /** PaymentMethod for 'Invoice' */
    const ID_INVOICE = 7;
}
