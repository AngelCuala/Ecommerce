<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerViolation extends Model
{
    protected $fillable = [
        'seller_id', 'admin_id', 'book_id',
        'type', 'action', 'note', 'acknowledged',
    ];

    public function seller(): BelongsTo { return $this->belongsTo(User::class,  'seller_id'); }
    public function admin(): BelongsTo  { return $this->belongsTo(User::class,  'admin_id'); }
    public function book(): BelongsTo   { return $this->belongsTo(Book::class,  'book_id'); }

    public static function typeLabels(): array
    {
        return [
            'wrong_category'        => 'Wrong Category',
            'prohibited_product'    => 'Prohibited Product',
            'inappropriate_content' => 'Inappropriate Content',
            'misleading_info'       => 'Misleading Information',
            'other'                 => 'Other',
        ];
    }

    public static function actionLabels(): array
    {
        return [
            'warning'             => 'Warning',
            'product_removed'     => 'Product Removed',
            'account_suspended'   => 'Account Suspended',
            'account_deactivated' => 'Account Deactivated',
        ];
    }

    public function typeLabel(): string  { return self::typeLabels()[$this->type]   ?? ucfirst($this->type); }
    public function actionLabel(): string{ return self::actionLabels()[$this->action] ?? ucfirst($this->action); }

    public function actionColor(): array
    {
        return match($this->action) {
            'warning'             => ['bg'=>'#FFFBEB','text'=>'#D97706'],
            'product_removed'     => ['bg'=>'#FFF0E6','text'=>'#FF6300'],
            'account_suspended'   => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
            'account_deactivated' => ['bg'=>'#F3F4F6','text'=>'#6B7280'],
            default               => ['bg'=>'#F5F5F5','text'=>'#555555'],
        };
    }
}
