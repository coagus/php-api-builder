<?php
namespace ApiBuilder;

use ApiBuilder\ORM\DataBase;
use ApiBuilder\PublicResource;

class Health
{
  #[PublicResource]
  public function get()
  {    
    success('API is Ready!');
  }

  #[PublicResource]
  public function getDatabase()
  {
    $db = new DataBase();
    $result = $db->query("SELECT 'Database is Ready!' AS message FROM dual");
    if (empty($result))
      error('Database is not Ready!');
    success($result->message);
  }
}