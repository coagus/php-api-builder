<?php
namespace DemoApi;

class Cors
{
  public function getAllowedOrigins()
  {
    return ['https://agustin.gt', 'http://localhost:3000'];
  }
}