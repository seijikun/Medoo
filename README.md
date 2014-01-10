# Medoo

The Lightest PHP database framework to accelerate development

### Main Features

* **Lightweight** - Only 10KB with one file.

* **Easy** - Extremely easy to learn and use, friendly construction.

* **Powerful** - Support various common SQL queries.

* **Compatible** - Support various SQL database, including MySQL, MSSQL, SQLite, MariaDB and more.

* **Security** - Prevent SQL injection.

* **Free** - Under MIT license, you can use it anywhere if you want.

### Get Started

    // Include Medoo
    require_once 'medoo.php';
    
    // Initialize
    $database = new medoo('my_database');
    
    // Enjoy
    $database->insert('account', [
        'user_name' => 'foo'
        'email' => 'foo@bar.com',
        'age' => 25,
        'lang' => ['en', 'fr', 'jp', 'cn']
    ]);

    // Or initialize via independent configuration
    $database = new medoo([
        'database_type' => 'mysql',
        'database_name' => 'name',
        'server' => 'localhost',
        'username' => 'your_username',
        'password' => 'your_password',
    ]);

### Links

* Official website: [http://medoo.in](http://medoo.in)

* Documentation: [http://medoo.in/doc](http://medoo.in/doc)

### Forked Features

* **Column Aliasing** - Use '[AS]' in the columnname-string to alias the column. (e.g. 'table1.column1[AS]column2')

* **QueryAssembler** - Complete Documentation about this can be found at the bottom of medoo.php. This is only supporting SELECT-Queries at the moment but is intended to support more later.
