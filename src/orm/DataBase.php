<?php
namespace ApiBuilder\ORM;

use PDO;
use PDOException;

class DataBase
{
  private $pdo;

  public function __CONSTRUCT()
  {
    if (!inEnv(HOST) || !inEnv(DBNAME) || !inEnv(CHARSET) || !inEnv(USERNAME) || !inEnv(PASSWORD))
      error('Database environment Error.');

    try {
      $dsn = 'mysql:host=' . $_ENV[HOST] . ';dbname=' . $_ENV[DBNAME] . ';charset=' . $_ENV[CHARSET];
      $this->pdo = new PDO($dsn, $_ENV[USERNAME], $_ENV[PASSWORD]);
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
      logError(SC_ERROR_NOT_FOUND, $e->getMessage(), $e->getFile(), $e->getLine());
      error("Database connection Error.");
    }
  }

  public function mutation($query)
  {
    try {
      $stm = $this->pdo->prepare($query);
      return $stm->execute();
    } catch (PDOException $e) {
      logError(SC_ERROR_NOT_FOUND, $e->getMessage(), $e->getFile(), $e->getLine());
      error("Database Mutation Error.");
    }
  }

  public function query($query, $single = true, $order = '')
  {
    try {
      if ($single) {
        $stm = $this->pdo->prepare($query);
        $stm->execute();
        return $stm->fetch(PDO::FETCH_OBJ);
      }

      $qryCount = "SELECT COUNT(1) as quantity FROM ($query) query";
      $stm = $this->pdo->prepare($qryCount);
      $stm->execute();
      $total = intval($stm->fetch(PDO::FETCH_OBJ)->quantity);

      $rowsPerPage =
        !isset($_REQUEST['rowsPerPage']) ? '10' 
        : ($_REQUEST['rowsPerPage'] == '-1' ? $total : $_REQUEST['rowsPerPage']);

      $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '0';
      $start = strval($page) * strval($rowsPerPage);

      $query = "SELECT * FROM ($query) query";
      $query .= $order == '' ? '' : ' ORDER BY ' . $order;
      $query .= " LIMIT $start, $rowsPerPage";

      $stm = $this->pdo->prepare($query);
      $stm->execute();
      $data = $stm->fetchAll(PDO::FETCH_OBJ);

      return [
        "pagination" => [
          "count" => $total,
          "page" => $page,
          "rowsPerPage" => $rowsPerPage
        ],
        "data" => $data
      ];
    } catch (PDOException $e) {
      logError(SC_ERROR_NOT_FOUND, $e->getMessage(), $e->getFile(), $e->getLine());
      error("Database Query Error.");
    }
  }

  public function lastInsertId()
  {
    return $this->pdo->lastInsertId();
  }

  public function existsEntity($entity)
  {
    $qry = "SELECT COUNT(1) AS cnt FROM information_schema.tables 
            WHERE table_schema = '" . $_ENV[DBNAME] . "' 
            AND table_name = '$entity'";

    return $this->query($qry)->cnt == '1';
  }
}