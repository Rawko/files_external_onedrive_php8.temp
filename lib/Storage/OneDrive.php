<?php
/**
 * @author Mario Perrotta <mario.perrotta@unimi.it>
 *
 * @copyright Copyright (c) 2018, Mario Perrotta <mario.perrotta@unimi.it>
 * @license GPL-2.0
 * 
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace OCA\Files_external_onedrive\Storage;

use Icewind\Streams\IteratorDirectory;
use Icewind\Streams\RetryWrapper;
use OCP\Files\Storage\FlysystemStorageAdapter;
use GuzzleHttp\Client as GuzzleHttpClient;
use Microsoft\Graph\Graph;
	
class OneDrive extends \OC\Files\Storage\Flysystem {

    const APP_NAME = 'files_external_onedrive';

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $clientSecret;

    /**
     * @var string
     */
    protected $accessToken;

    /**
     * @var Client
     */
    private $client;
    private $id;
	private $options;
	protected $adapter;
	protected $logger;
	
	private static $tempFiles = [];

	/**
	* Initialize the storage backend with a flysytem adapter
	* @override
	* @param \League\Flysystem\Filesystem $fs
	*/
	public function setFlysystem($fs)
	{
		$this->flysystem = $fs;
		$this->flysystem->addPlugin(new \League\Flysystem\Plugin\GetWithMetadata());
	}
	public function setAdapter($adapter)
	{
		$this->adapter = $adapter;
	}

	public function __construct($params) {
        if (isset($params['client_id']) && isset($params['client_secret']) && isset($params['token'])
            && isset($params['configured']) && $params['configured'] === 'true'
        ) {
            $this->clientId = $params['client_id'];
            $this->clientSecret = $params['client_secret'];
			
			$this->token = json_decode($params['token']);

			$this->accessToken = $this->token->access_token;

			$this->client = new Graph();
			$this->client->setAccessToken($this->accessToken);
		
			$this->root = isset($params['root']) ? $params['root'] : '/';
			$this->id = 'onedrive::' . $this->clientId; //. '/' . $this->root;
			$this->adapter = new Adapter($this->client, 'root', '/me/drive/', true);
			$this->buildFlySystem($this->adapter);
			$this->logger = \OC::$server->getLogger();

        } else if (isset($params['configured']) && $params['configured'] === 'false') {
            throw new \Exception('OneDrive storage not yet configured');
        } else {
            throw new \Exception('Creating OneDrive storage failed');
        }

	}

	public function getId() {
		return $this->id;
	}

    public function test()
    {
		// TODO: add test Storage
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function filemtime($path) {
		if ($this->is_dir($path)) {
			return $this->adapter->getTimestamp($path);
		} else {
			return parent::filemtime($path);
		}
	}

	public function file_exists($path) {
        if ($path === '' || $path === '/' || $path === '.') {
            return true;
        }
        return parent::file_exists($path);
	}
	
	protected function getLargest($arr, $default = 0) {
		if (\count($arr) === 0) {
			return $default;
		}
		\arsort($arr);
		return \array_values($arr)[0];
	}

}