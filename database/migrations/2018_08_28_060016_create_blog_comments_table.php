<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBlogCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*Schema::create('blog_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');           
            $table->integer('parent_comment_id');
            $table->integer('blog_id');
            $table->text('comment');
            $table->timestamps();
        });*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Schema::dropIfExists('blog_comments');
    }
}
