<?php

namespace TCG\Voyager\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use TCG\Voyager\Models\User as User;
use TCG\Voyager\Models\DataType as DataType;
use Intervention\Image\Facades\Image as Image;
use \Storage;

class VoyagerBreadController extends Controller
{
    //***************************************
    //               ____
    //              |  _ \
    //              | |_) |
    //              |  _ <
    //              | |_) |
    //              |____/
    //
    //      Browse our Data Type (B)READ
    //
    //****************************************

    public function index(Request $request)
    {
        // GET THE SLUG, ex. 'posts', 'pages', etc.
        $slug = $request->segment(2);

        // GET THE DataType based on the slug
        $dataType = DataType::where('slug', '=', $slug)->first();

        // Next Get the actual content from the MODEL that corresponds to the slug DataType
        if (strlen($dataType->model_name) != 0)
        {
            $dataTypeContent = call_user_func([$dataType->model_name, 'all']);
        }
        else
        {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->get();
        }


        if (view()->exists('admin.' . $slug . '.browse')) {
            return view('admin.' . $slug . '.browse',
                ['dataType' => $dataType, 'dataTypeContent' => $dataTypeContent]);
        } else {
            if (view()->exists('voyager::' . $slug . '.browse')) {
                return view('voyager::' . $slug . '.browse',
                    ['dataType' => $dataType, 'dataTypeContent' => $dataTypeContent]);
            } else {
                return view('voyager::bread.browse',
                    ['dataType' => $dataType, 'dataTypeContent' => $dataTypeContent]);
            }
        }

    }

    //***************************************
    //                _____
    //               |  __ \
    //               | |__) |
    //               |  _  /
    //               | | \ \
    //               |_|  \_\
    //
    //  Read an item of our Data Type B(R)EAD
    //
    //****************************************

    public function show(Request $request, $id)
    {
        $slug = $request->segment(2);
        $dataType = DataType::where('slug', '=', $slug)->first();
        
        if (strlen($dataType->model_name) != 0)
        {
            $dataTypeContent = call_user_func([$dataType->model_name, 'find'], $id);
        }
        else
        {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id',$id)->first();
        }

        return view('voyager::bread.read', ['dataType' => $dataType, 'dataTypeContent' => $dataTypeContent]);
    }

    //***************************************
    //                ______
    //               |  ____|
    //               | |__
    //               |  __|
    //               | |____
    //               |______|
    //
    //  Edit an item of our Data Type BR(E)AD
    //
    //****************************************

    public function edit(Request $request, $id)
    {
        $slug = $request->segment(2);
        $dataType = DataType::where('slug', '=', $slug)->first();
        
        if (strlen($dataType->model_name) != 0)
        {
            $dataTypeContent = call_user_func([$dataType->model_name, 'find'], $id);
        }
        else
        {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id',$id)->first();
        }


        if (view()->exists('admin.' . $slug . '.edit-add')) {
            return view('admin.' . $slug . '.edit-add',
                ['dataType' => $dataType, 'dataTypeContent' => $dataTypeContent]);
        } else {
            if (view()->exists('voyager::' . $slug . '.edit-add')) {
                return view('voyager::' . $slug . '.edit-add',
                    ['dataType' => $dataType, 'dataTypeContent' => $dataTypeContent]);
            } else {
                return view('voyager::bread.edit-add',
                    ['dataType' => $dataType, 'dataTypeContent' => $dataTypeContent]);
            }
        }


    }

    // POST BR(E)AD
    public function update(Request $request, $id)
    {
        $slug = $request->segment(2);
        $dataType = DataType::where('slug', '=', $slug)->first();
        $data = call_user_func([$dataType->model_name, 'find'], $id);
        $this->insertUpdateData($request, $slug, $dataType->editRows, $data);
        return redirect(route($dataType->slug . '.index'))->with([
            'message' => 'Successfully Updated ' . $dataType->display_name_singular,
            'alert-type' => 'success'
        ]);
    }

    //***************************************
    //
    //                   /\
    //                  /  \
    //                 / /\ \
    //                / ____ \
    //               /_/    \_\
    //
    //
    // Add a new item of our Data Type BRE(A)D
    //
    //****************************************

    public function create(Request $request)
    {
        $slug = $request->segment(2);
        $dataType = DataType::where('slug', '=', $slug)->first();
        if (view()->exists('admin.' . $slug . '.edit-add')) {
            return view('admin.' . $slug . '.edit-add', ['dataType' => $dataType]);
        } else {
            if (view()->exists('voyager::' . $slug . '.edit-add')) {
                return view('voyager::' . $slug . '.edit-add', ['dataType' => $dataType]);
            } else {
                return view('voyager::bread.edit-add', ['dataType' => $dataType]);
            }
        }
    }

    // POST BRE(A)D
    public function store(Request $request)
    {
        //
        $slug = $request->segment(2);
        $dataType = DataType::where('slug', '=', $slug)->first();

        if (function_exists('voyager_add_post')) {
            $url = $request->url();
            voyager_add_post($request);
        }

        $data = new $dataType->model_name;
        $this->insertUpdateData($request, $slug, $dataType->addRows, $data);
        return redirect(route($dataType->slug . '.index'))->with([
            'message' => 'Successfully Added New ' . $dataType->display_name_singular,
            'alert-type' => 'success'
        ]);
    }

    //***************************************
    //                _____
    //               |  __ \
    //               | |  | |
    //               | |  | |
    //               | |__| |
    //               |_____/
    //
    //         Delete an item BREA(D)
    //
    //****************************************

    public function destroy(Request $request, $id)
    {
        $slug = $request->segment(2);
        $dataType = DataType::where('slug', '=', $slug)->first();

        $data = call_user_func([$dataType->model_name, 'find'], $id);

        foreach ($dataType->deleteRows as $row) {
            if ($row->type == 'image') {
                if (\Storage::exists('/uploads/' . $data->{$row->field})) {
                    Storage::delete('/uploads/' . $data->{$row->field});
                }

                $options = json_decode($row->details);

                if (isset($options->thumbnails)) {
                    foreach ($options->thumbnails as $thumbnail) {
                        $ext = explode('.', $data->{$row->field});
                        $extension = '.' . $ext[count($ext) - 1];

                        $path = str_replace($extension, '', $data->{$row->field});

                        $thumb_name = $thumbnail->name;

                        if (Storage::exists('/uploads/' . $path . '-' . $thumb_name . $extension)) {
                            Storage::delete('/uploads/' . $path . '-' . $thumb_name . $extension);
                        }

                    }  // end if isset
                } // end if storage
            } // end if row->type
        } // end foreach

        if ($data->destroy($id)) {
            return redirect(route($dataType->slug . '.index'))->with([
                'message' => 'Successfully Deleted ' . $dataType->display_name_singular,
                'alert-type' => 'success'
            ]);
        }

        return redirect(route($dataType->slug . '.index'))->with([
            'message' => 'Sorry it appears there was a problem deleting this ' . $dataType->display_name_singular,
            'alert-type' => 'error'
        ]);

    } // end of destroy()

    public function insertUpdateData($request, $slug, $rows, $data)
    {
        $rules = [];
        foreach ($rows as $row) {
            $options = json_decode($row->details);
            if (isset($options->rule)) {
                $rules[$row->field] = $options->rule;
            }

            $content = $this->getContentBasedOnType($request, $slug, $row);
            if ($content === null) {
                if (isset($data->{$row->field})) {
                    $content = $data->{$row->field};
                }
                if ($row->field == 'password') {
                    $content = $data->{$row->field};
                }
            }

            $data->{$row->field} = $content;
        }

        $this->validate($request, $rules);

        $data->save();

    }

    public function getContentBasedOnType($request, $slug, $row)
    {
        /********** PASSWORD TYPE **********/
        if ($row->type == 'password') {
            $pass_field = $request->input($row->field);
            if (isset($pass_field) && !empty($pass_field)) {
                $content = bcrypt($request->input($row->field));
            } else {
                $content = null;
            }

            /********** CHECKBOX TYPE **********/
        } else {
            if ($row->type == 'checkbox') {
                $content = 0;
                $checkBoxRow = $request->input($row->field);

                if (isset($checkBoxRow)) {
                    $content = 1;
                }

                /********** FILE TYPE **********/
            } else {
                if ($row->type == 'file') {

                    $file = $request->file($row->field);
                    $filename = str_random(20);

                    $path = $slug . '/' . date('F') . date('Y') . '/';

                    $full_path = $path . $filename . '.' . $file->getClientOriginalExtension();

                    Storage::put(config('voyager.storage.subfolder') . $full_path, (string)$file, 'public');

                    $content = $full_path;

                    /********** IMAGE TYPE **********/
                } else {
                    if ($row->type == 'image') {


                        if ($request->hasFile($row->field)) {

                            $storage_disk = 'local';

                            $file = $request->file($row->field);
                            $filename = str_random(20);

                            $path = $slug . '/' . date('F') . date('Y') . '/';
                            $full_path = $path . $filename . '.' . $file->getClientOriginalExtension();

                            $options = json_decode($row->details);

                            if (isset($options->resize) && isset($options->resize->width) && isset($options->resize->height)) {
                                $resize_width = $options->resize->width;
                                $resize_height = $options->resize->height;
                            } else {
                                $resize_width = 1800;
                                $resize_height = null;
                            }

                            $image = Image::make($file)->resize($resize_width, $resize_height, function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            })->encode($file->getClientOriginalExtension(), 75);

                            Storage::put(config('voyager.storage.subfolder') . $full_path, (string)$image, 'public');

                            if (isset($options->thumbnails)) {
                                foreach ($options->thumbnails as $thumbnails) {

                                    if (isset($thumbnails->name) && isset($thumbnails->scale)) {
                                        $scale = intval($thumbnails->scale) / 100;
                                        $thumb_resize_width = $resize_width;
                                        $thumb_resize_height = $resize_height;
                                        if ($thumb_resize_width != 'null') {
                                            $thumb_resize_width = $thumb_resize_width * $scale;
                                        }
                                        if ($thumb_resize_height != 'null') {
                                            $thumb_resize_height = $thumb_resize_height * $scale;
                                        }
                                        $image = Image::make($file)->resize($thumb_resize_width, $thumb_resize_height,
                                            function ($constraint) {
                                                $constraint->aspectRatio();
                                                $constraint->upsize();
                                            })->encode($file->getClientOriginalExtension(), 75);


                                    } elseif (isset($options->thumbnails) && isset($thumbnails->crop->width) && isset($thumbnails->crop->height)) {
                                        $crop_width = $thumbnails->crop->width;
                                        $crop_height = $thumbnails->crop->height;
                                        $image = Image::make($file)->fit($crop_width,
                                            $crop_height)->encode($file->getClientOriginalExtension(), 75);
                                    }

                                    Storage::put(config('voyager.storage.subfolder') . $path . $filename . '-' . $thumbnails->name . '.' . $file->getClientOriginalExtension(),
                                        (string)$image, 'public');
                                }
                            }

                            $content = $full_path;

                        } else {

                            $content = null;

                        }

                        /********** ALL OTHER TEXT TYPE **********/
                    } else {
                        $content = $request->input($row->field);
                    }
                }
            }
        }

        return $content;
    }

    public function generate_views(Request $request)
    {
        //$dataType = DataType::where('slug', '=', $slug)->first();
    }
}
