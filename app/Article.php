<?php


namespace App;


use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $table = 'news';

    protected $fillable = [
        'title', 'description', 'author', 'url', 'source',
    ];
}