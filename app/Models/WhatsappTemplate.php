<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'template_key',
        'content',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function render(array $params): string
    {
        $rendered = $this->content;
        foreach ($params as $key => $value) {
            $rendered = str_replace('{' . $key . '}', (string) $value, $rendered);
        }
        return $rendered;
    }
}
