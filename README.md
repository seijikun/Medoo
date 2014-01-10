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

```php
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
```

### Links

* Official website: [http://medoo.in](http://medoo.in)

* Documentation: [http://medoo.in/doc](http://medoo.in/doc)

### Forked Features

* **Column Aliasing** - Use '[AS]' in the columnname-string to alias the column. (e.g. 'table1.column1[AS]column2')

* **QueryAssembler** - Complete Documentation about this can be found at the bottom of medoo.php. Generally it's following medoo's api design except for seperating parameters e.g. LIMIT, ORDER or GROUP out of WHERE and some additional features like ORDER BY FIELD as well as the NOT equal operator ("!=" instead of "!") and column ALIASing. This is only supporting SELECT-Queries at the moment but is intended to support more later.

```php
    //for initialisation - see above
    
    $table = 'myTable';
    $columns = ['myColumn1', 'myColumn2'];
    $joins = [ '[<]myJoinTable' => 'myTable.myColumn1'] ]; //right join
    $where = [ 'AND' => ['iq[<>]' => [1337,31337] ];
    $group = 'myTable.myColumn4';
    $having = [ 'email[!=]' => 'foo@bar.com' ]; //caution! "!=" instead of "!"
    $order = [ 'myColumn2' => [2, 3, 1], 'myColumn ASC' ];
    $limit = [1337, 31337];
    
    $query = new SelectQuery($database, $table, $columns, $joins, $where, $group, $having, $order, $limit);
    
    $result = $database->query($query->toString());
```
