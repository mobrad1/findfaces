<?php


namespace App\Services;


use App\Models\Face;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
class FindFaces
{
    public $collectionId = 'find-faces';
    public function index()
    {
        $faces = Face::all();
        return response()->json($faces);
    }
    public function store()
    {
        $url = $this->uploadImageToS3();
        $faceIndexed = $this->indexFace($url);

        if($faceIndexed){
            $face = Face::create([
                "url" => $url,
                "description" => request('description'),
                "title" => request("title"),
                "external_image_id" => $this->getName($url)
            ]);
            return $face;
        }
        return response()->json(["message" => "Failed to index faces"],"400");

    }
    public function uploadImageToS3()
    {
        $hashName = request()->file->hashName();
        $s3 = App::make('aws')->createClient('s3');
        $uploadImage = $s3->putObject(array(
            'Bucket'     => config('aws.bucket'),
            'Key'        => $hashName,
            'SourceFile' => request()->file('file')->path(),
        ));
        return $uploadImage["ObjectURL"];
    }

    public function getName($url)
    {
        $fish = str_replace('https://find-faces.s3.eu-central-1.amazonaws.com/', '', $url);
        $replaceSpace = str_replace('%20', ' ', $fish);
        $replaceColon = str_replace('%3A', ':', $replaceSpace);
        $replaceComma = str_replace('%27', "'", $replaceColon);

        return $replaceComma;
    }
    public function indexFace($url)
    {

        $rekognition = App::make('aws')->createClient('rekognition');


        $rekognition->describeCollection(['CollectionId'=> $this->collectionId]);
        try {
            $rekognition->indexFaces([
                    'CollectionId'=> $this->collectionId,
                    'ExternalImageId'=> $this->getName($url),
                    'Image'=>[
                        'S3Object'=>[
                            'Bucket'=>config('aws.bucket'),
                            'Name' => $this->getName($url),
                        ],
                    ],
                ]);
        } catch (\Exception $e) {
            return response()->json(['status'=>404, 'message'=>'Rekognition indexing Failed', 'data'=>$e->getMessage()]);
        }
        return true;
    }
    public function search()
    {
        $rekognition = App::make('aws')->createClient('rekognition');
        $url = $this->uploadImageToS3();
        $faces = $rekognition->searchFacesByImage([
                    'CollectionId'=>$this->collectionId,
                    'FaceMatchThreshold'=>99,
                    'Image'=>[
                        'S3Object'=>[
                            'Bucket'=>config('aws.bucket'),
                            'Name'=>$this->getName($url),
                        ],
                    ],
        ]);
        $imagesFound = [];
        foreach ($faces['FaceMatches'] as $face){
            $imagesFound[] = $face['Face']['ExternalImageId'];
        }
        return Face::whereIn('external_image_id',$imagesFound)->get();
    }
}
