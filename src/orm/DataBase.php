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
      error("Database connection Error:" . $e->getMessage());
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
      return false;
    }
  }

  public function query($query, $single = true, $order = '')
  {
    try {
      if ($single)
        return $this->pdo->query($query)->fetch(PDO::FETCH_OBJ);

      $qryCount = "SELECT COUNT(1) as cnt FROM ($query) query";
      $count = intval($this->pdo->query($qryCount)->fetch(PDO::FETCH_OBJ)->cnt);

      $page = $_REQUEST['page'] ?? '0';
      $rowsPerPage = $_REQUEST['rowsPerPage'] ?? '10';
      $rowsPerPage = $rowsPerPage === '-1' ? $count : $rowsPerPage;
      $start = (int) $page * (int) $rowsPerPage;

      $query = "SELECT * FROM ($query) query" . ($order ? " ORDER BY $order" : "") . " LIMIT $start, $rowsPerPage";
      $data = $this->pdo->query($query)->fetchAll(PDO::FETCH_OBJ);

      return [
        "pagination" => [
          "count" => $count,
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
    $query = "SELECT COUNT(1) AS cnt FROM information_schema.tables 
            WHERE table_schema = '" . $_ENV[DBNAME] . "' 
            AND table_name = '$entity'";

    return $this->query($query)->cnt == '1';
  }
}