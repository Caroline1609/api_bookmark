<?php // /index.php

function main()
{
	$response = new response;
	$response->content_type('application/json');

	$path = explode('/', $_SERVER['REQUEST_URI']);
	if('' !== $path[0])
	{
		$response->bad_request();
		$response->body('Pas bon');
		$response->end();
	}
	array_shift($path);

	$entry_point = array_shift($path);
	if(!in_array($entry_point, ['bookmarks'], true))
	{
		$response->not_found();
		$response->json_body(['error' => sprintf('Entry point \'%s\' not found', $entry_point)]);
		$response->end();
	}

	switch($entry_point)
	{
		case 'bookmarks':
			//$source = new bookmarks;
			$db = DbConnection::getInstance();
			$source = new bb($db);

			$id = array_shift($path);
			if(!is_numeric($id) && !is_null($id))
			{
				$response->not_found();
				$response->json_body(['error' => 'Resource not found']);
				$response->end();
			}

			$http_method = $_SERVER['REQUEST_METHOD'];
			if('OPTIONS' === $http_method) {
				// Réponse rapide pour la préflight CORS
				$response->end();
			}

			if(is_null($id) && 'GET' === $http_method)
			{
				$payload = $source->all();
				$response->json_body($payload);
				$response->end();
			}

			$id = (int) $id;

			try
			{
				$bookmark = $source->get_one($id);
			}
			catch(bookmark_exception $e)
			{
				$response->not_found();
				$response->end();
			}

			switch($http_method)
			{
				case 'GET':
					$response->json_body($bookmark);
					$response->end();
					break;
				case 'POST':
				case 'PUT':
					if(!is_null($id))
					{
						$payload = file_get_contents('php://input');
						//if(false === $payload)
						$payload = json_decode($payload, JSON_THROW_ON_ERROR);
						$bookmark = new bookmark(
							title: $payload['title'],
							url: $payload['url'],
							description: $payload['description'] ?? null);
							

						$source->add_one($bookmark);
						$response->location(sprintf('/bookmarks/%d', $bookmark->id));
						$response->end();
					}

					break;
				case 'DELETE':
					if($source->delete_one($bookmark))
					{
						//$response->location('/bookmarks');
						$response->end();
					}

					$response->not_found();
					$response->end();
					break;
				default:
					$response->method_not_allowed();
					$response->json_body(['error' => sprintf('Method \'%s\' on entry point \'%s\' not supported', $http_method, $entry_point)]);
					$response->end();
			}

			break;
	}
}

class response
{
	public function __construct(
		public array $header = [],
		public string $body = '',
	)
	{
		$this->header[] = 'Access-Control-Allow-Origin: http://127.10.10.10';
		$this->header[] = 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS';
		$this->header[] = 'Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, Authorization';
	}

	function content_type(string $mime): void
	{
		$this->header[] = sprintf('Content-type: %s', $mime);
	}

	public function not_found()
	{
		$this->header[] = 'HTTP/1.1 404 Not Found';
	}

	public function method_not_allowed()
	{
		$this->header[] = 'HTTP/1.1 405 Method not allowed';
	}

	public function bad_request()
	{
		$this->header[] = 'HTTP/1.1 400 Bad Request';
	}

	public function body(string $payload): void
	{
		$this->body = $payload;
	}

	public function json_body(array|string|jsonserializable $payload): void
	{
		$this->body = json_encode($payload, JSON_THROW_ON_ERROR);
	}

	public function location(string $location)
	{
		$this->header[] = sprintf('Location: %s', $location); // XXX Header injection?!!
	}

	public function end(): void
	{
		foreach($this->header as $h)
			header($h);

		die($this->body);
	}
}

class bookmark implements jsonserializable
{
	public function __construct(
		public ?int $id = null,
		public ?string $url = null,
		public ?string $title = null,
		public ?string $description = null
	)
	{
	}

	public function jsonSerialize(): mixed
	{
		return [ 'URL' => $this->url
			, 'Title' => $this->title
			, '_id' => $this->id
			, 'Description' => $this->description
		];
	}
}

interface bookmark_collection
{
	function all(): array;
	function get_one(int $id): ?bookmark;
	#[nodiscard]
	function delete_one(bookmark $b): bool;
	function add_one(bookmark $b): bool;
}

class bookmark_exception extends exception
{
}

final class DbConnection
{
    private static ?PDO $connexion = null;

    private function __construct() {} 

    public static function getInstance(): PDO
    {
        if (self::$connexion === null) {
            
            $host = 'db';
            $base = getenv('DAMP_DATABASE_NAME'); 
            $user = getenv('DAMP_DATABASE_USERNAME');
			$port = getenv('DAMP_DATABASE_PORT');
            $pass = getenv('DAMP_DATABASE_PASSWORD'); 

            try {
                self::$connexion = new PDO(
                    "mysql:host=$host;dbname=$base;port=$port;charset=utf8",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (PDOException $e) {
                die("Erreur de connexion : " . $e->getMessage());
            }
        }
        return self::$connexion;
    }
}


class bb implements bookmark_collection
{

	private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

	public function all(): array
    {
		$stmt = $this->db->query("SELECT id, url, title, description FROM bookmark");
        //$toto = $stmt->fetchAll(PDO::FETCH_CLASS, bookmark::class);
		return $stmt->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, bookmark::class);
    }

	function get_one(int $id): ?bookmark
    {
		$stmt = $this->db->prepare("SELECT id, url, title, description FROM bookmark WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, bookmark::class);
        $bookmark = $stmt->fetch();
        return $bookmark ?: null;
    }

	#[nodiscard]
	function delete_one(bookmark $b): bool
	{
		$stmt = $this->db->prepare("DELETE FROM bookmark WHERE id = :id");
    	return $stmt->execute(['id' => $b->id]);
	}

	function add_one(bookmark $b): bool
	{
		$stmt = $this->db->prepare("INSERT INTO bookmark (url, title, description) VALUES (:url, :title, :description)");

		$success = $stmt->execute([
			'url'   => $b->url,
			'title' => $b->title,
			'description' => $b->description
		]);

		if ($success) {
			$b->id = (int) $this->db->lastInsertId();
		}

		return $success;
	}
		

}

	










class bookmarks implements bookmark_collection
{
	public function __construct(
		private array $all = []
	)
	{
		$this->all = include 'bookmark_fixtures.php';
	}

	function all(): array
	{
		return $this->all;
	}

	function get_one(int $id): bookmark
	{
		foreach($this->all as $b)
		{
			if($id === $b->id)
				return $b;
		}

		throw new bookmark_exception('Y\'a pas!');
	}

	function add_one(bookmark $b): bool
	{
		$b->id = rand(0, 1024);
		$this->all[] = $b;

		return true;
	}

	#[nodiscard]
	function delete_one(bookmark $b): bool
	{
		foreach($this->all as $k => $v)
			if($v->id === $b->id)
			{
				unset($this->all[$k]);
				return true;
			}

		return false;
	}
}

main();
