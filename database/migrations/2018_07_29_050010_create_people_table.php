<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePeopleTable extends Migration {

    protected $schema;

    public function __construct()
    {
        $this->schema = Schema::connection(config('database.trybot2000'));
    }

	public function up()
	{
		$this->schema->create('people', function(Blueprint $table)
		{
			$table->integer('primary', true);
			$table->string('gamertag', 254)->unique('gamertag');
			$table->string('displayName', 254)->nullable();
			$table->string('groupMeId', 254)->nullable();
			$table->string('codUserId', 254)->nullable();
			$table->string('emailAddress', 254)->nullable();
			$table->string('redditUserName', 254)->nullable();
			$table->string('notes', 254)->nullable();
			$table->dateTime('dateJoined')->nullable();
			$table->string('introPost')->nullable();
			$table->string('altFor', 254)->nullable();
		});
	}


	public function down()
	{
		$this->schema->drop('people');
	}

}
