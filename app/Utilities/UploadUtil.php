<?php namespace Utilities;
use Exception;
use UserUtil;

class UploadUtil
{
	public $db = null;

	public $jwt = null;
	
	public $imgPath = 'public/img/';

	public function __construct( $db = null, $jwtToken, $manifest ) {
	
		$this->db = $db;
		$this->jwtToken = $jwtToken;
		$this->manifest = $manifest;

		$this->db = $db;
		
		//$this->jwt = $jwt;

	}

	//check if uuid for valid user
	public function upload ($args, $request ) {

		$requestData = $request->getParsedBody();

		if( !method_exists($this, $args['type']) ) throw new Exception("Upload method ".$args['type']." Not Found!");

		try {
				
			//Call the upload method
			$file = $this->{$args['type']}($requestData);

			// if error
			if (!is_object($file)) return $file;

			// Success!
			if ($file->upload()) return $file;
			
			return false;
				
		} catch (Exception $e) {
				
			// Fail!
			return false; //$e->getMessage();
				
		}



	}

	private function UserPhoto ($requestData) {

		$this->imgPath = $this->imgPath . "user/";
		
		$storage = new \Upload\Storage\FileSystem($this->imgPath);
		$file = new \Upload\File('photo', $storage);

		// Optionally you can rename the file on upload
		$new_filename = $this->jwtToken->user->uuid;//$requestData['user_uuid'];
		$file->setName($new_filename);
		
		// Validate file upload
		// MimeType List => http://www.iana.org/assignments/media-types/media-types.xhtml
		$file->addValidations(array(
				// Ensure file is of type "image/png"
				//new \Upload\Validation\Mimetype('image/png'),

				//You can also add multi mimetype validation
				new \Upload\Validation\Mimetype(array('image/png', 'image/gif', 'image/jpg', 'image/jpeg')),

				// Ensure file is no larger than 5M (use "B", "K", M", or "G")
				new \Upload\Validation\Size('5M')
		));

		// Access data about the file that has been uploaded
		$data = array(
				'name'       => $file->getNameWithExtension(),
				'extension'  => $file->getExtension(),
				'mime'       => $file->getMimetype(),
				'size'       => $file->getSize(),
				'md5'        => $file->getMd5(),
				'dimensions' => json_encode($file->getDimensions())
		);

		$data['path'] = $this->imgPath;

		try {

			// INSERT INTO users ( id , usr , pwd ) VALUES ( ? , ? , ? )
			$updataeStatement = $this->db->update( array('photo' => json_encode($data)) )
										->table('users')
										->where('uuid', '=', $this->jwtToken->user->uuid);
			$updateId = $updataeStatement->execute(true);

		} catch (Exception $e) {

			throw new Exception($e->getMessage());

		}

 		$filePath = $this->imgPath . $this->jwtToken->user->uuid . "." . $file->getExtension();
 		if(file_exists($filePath))
 			unlink($filePath);

		return $file;

	}

	private function MenuPhoto ($requestData) {

		$this->imgPath = $this->imgPath . "menu/";

		//Get all menu details
		$objMenu = $this->db->select()->from('menus')->where('id','=',$requestData['menu_id']);
		$objMenu = $objMenu->execute(false);
		if (!$objMenu->fetch()) return "Menu Not Found";

		$storage = new \Upload\Storage\FileSystem($this->imgPath);
		$file = new \Upload\File('photo', $storage);

		// Optionally you can rename the file on upload
		$new_filename = $requestData['menu_id']."_". preg_replace('/\s+/', '', $file->getName());
		$file->setName($new_filename);

		// Validate file upload
		$file->addValidations(array(
				new \Upload\Validation\Mimetype(array('image/png', 'image/gif', 'image/jpg', 'image/jpeg')),
				new \Upload\Validation\Size('5M')
		));

		// Access data about the file that has been uploaded
		$data = array(
				'name'       => $file->getNameWithExtension(),
				'extension'  => $file->getExtension(),
				'mime'       => $file->getMimetype(),
				'size'       => $file->getSize(),
				'md5'        => $file->getMd5(),
				'dimensions' => json_encode($file->getDimensions())
		);

		$data = array_merge($data, $requestData);
		$data['path'] = $this->imgPath;

		//save image to the menu_images table
		$arrFields = array_keys($data);
		$arrValues = array_values($data);

		try {

			// INSERT INTO users ( id , usr , pwd ) VALUES ( ? , ? , ? )
			$insertStatement = $this->db->insert( $arrFields )
										->into('menu_images')
										->values($arrValues);
			$insertId = $insertStatement->execute(true);

		} catch (Exception $e) {

			throw new Exception($e->getMessage());

		}

		$filePath = $this->imgPath . $new_filename . "." . $file->getExtension();
		if(file_exists($filePath)) unlink($filePath);		
		
		return $file;

	}
	
	
	private function VendorPhoto ($requestData) {
			
		$this->imgPath = $this->imgPath . "vendor/";
	
		$objUtil = new \Utilities\UserUtil($this->db);
		$intId = $objUtil->checkVendor($requestData['vendor_uuid']);
		if ( ! $intId ) {
			$response->withJson(array("status" => false, "message" =>"Record Not Found!"), 404);
			return $response;
		}
		
		$storage = new \Upload\Storage\FileSystem($this->imgPath);
		$file = new \Upload\File('photo', $storage);
		
		// Optionally you can rename the file on upload
		$new_filename = $requestData['vendor_uuid'];
		$file->setName($new_filename);

		// Validate file upload
		// MimeType List => http://www.iana.org/assignments/media-types/media-types.xhtml
		$file->addValidations(array(
				// Ensure file is of type "image/png"
				//new \Upload\Validation\Mimetype('image/png'),

				//You can also add multi mimetype validation
				new \Upload\Validation\Mimetype(array('image/png', 'image/gif', 'image/jpg', 'image/jpeg')),

				// Ensure file is no larger than 5M (use "B", "K", M", or "G")
				new \Upload\Validation\Size('5M')
		));

		// Access data about the file that has been uploaded
		$data = array(
				'name'       => $file->getNameWithExtension(),
				'extension'  => $file->getExtension(),
				'mime'       => $file->getMimetype(),
				'size'       => $file->getSize(),
				'md5'        => $file->getMd5(),
				'dimensions' => json_encode($file->getDimensions())
		);

		$data['path'] = $this->imgPath;
		
		try {

			// INSERT INTO users ( id , usr , pwd ) VALUES ( ? , ? , ? )
			$updataeStatement = $this->db->update( array('photo' => json_encode($data)) )
										->table('vendors')
										->where('id', '=', $intId);
			$updateId = $updataeStatement->execute(true);

		} catch (Exception $e) {

			throw new Exception($e->getMessage());

		}

		$filePath = $this->imgPath . $requestData['vendor_uuid'] . "." . $file->getExtension();
		if(file_exists($filePath))
			unlink($filePath);

		return $file;
	
	}	



}