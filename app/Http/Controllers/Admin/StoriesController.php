<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\StoryRequest;
use App\Http\Requests\ChangeNovelToStoryRequest;
use Carbon\Carbon;
use function Couchbase\defaultDecoder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Controller;
use App\Lib\Fmtables;
use App\Lib\FmForm;
use App\Models\Story;
use App\Models\Theme;
use App\Models\Subject;
use App\Models\SiteUser;
use App\Models\Rating;
use App\Models\StoryTheme;
use App\Models\StorySubject;
use App\Models\Rater;
use App\Models\Flag;
use App\Models\StoryCategory;
use App\Models\Comment;
use App\Models\StoryStar;
use App\Models\MonthAuthor;

use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;
use Avatar;
use Image;
use JsValidator;


class StoriesController extends Controller
{

    protected $pageData = array();
    public $singularName = 'Story';
    public $singularName1 = 'Novel';
    public $pluralName = 'Stories';
    public $pluralName1 = 'Novels';
    protected $jsValidation = true;
    protected $listViewSimple = ['created_timestamp', 'updated_timestamp', 'action'];
    protected $selectedViewType = false;
    protected $multipleDelete = true;


    public function __construct()
    {

        // echo round((0.6 * 2)) / 2;
        //echo request()->segment(2);

        $this->pageData['PageTitle'] = $this->pluralName . " List";
        $this->pageData['MainNav'] = $this->pluralName;
        $this->pageData['SubNav'] = "Manage Stories";

        // admin auth middleware
        $this->middleware('auth:admin');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index1(Builder $builder, Request $request)
    {
        /* Set and Get list view for this page */
        if ($this->selectedViewType)
            $this->selectedViewType = $this->listView($request);

        /* List View Data */
        if (request()->ajax()) {
            return $this->getAjaxData($request);
        }

        /* List UI Generator */
        $this->pageData['MainHeading'] = $this->singularName;


        // List Columns
        $viewColumns = [
            ['data' => 'story_id', 'name' => 'story_id', 'title' => '', 'searchable' => false, 'class' => 'text-center no-filter', 'width' => 50],
            ['data' => 'created_timestamp', 'name' => 'created_timestamp', 'title' => 'Published On', 'searchable' => true, 'class' => 'text-center', 'width' => 50],
            ['data' => 'story_id', 'name' => 'story_id', 'title' => 'Story ID', 'searchable' => true, 'class' => 'text-center', 'width' => 50],
            ['data' => 'story_nominations', 'name' => 'story_nominations', 'title' => 'Nominations', 'searchable' => false, 'class' => 'text-center', 'width' => 60],
            ['data' => 'story_title', 'name' => 'story_title', 'title' => 'Story', 'searchable' => false, 'data-class' => 'expand', 'class' => 'no-filter', 'width' => 700],
            //['data' => 'short_description', 'name' => 'short_description', 'title' => 'Short Description', 'data-class' => 'expand', 'width' => 200],
            //['data' => 'author_name', 'name' => 'author_name', 'title' => 'Author', 'data-class' => 'expand', 'width' => 200],
            //['data' => 'email', 'name' => 'email', 'title' => 'Email', 'data-class' => 'expand', 'width' => 200],
            //['data' => 'average_rate', 'name' => 'average_rate', 'title' => 'Rank', 'data-class' => 'expand', 'width' => 200],
            //['data' => 'views', 'name' => 'views', 'title' => 'Views', 'data-class' => 'expand', 'width' => 100],
            ['defaultContent' => '', 'data' => 'action', 'name' => 'action', 'title' => 'Action', 'render' => '', 'orderable' => false, 'searchable' => false, 'exportable' => false, 'printable' => false, 'footer' => '', 'class' => 'text-center', 'width' => '140']
        ];

        $html = $this->getDataTable($builder, "Story_TBL", $viewColumns, 'Avatar', 'admin-stories-list', [[1, "desc"]], $this->multipleDelete);

        $this->pageData['category_id'] = isset($request->category_id) ? $request->category_id : 0;
        $this->pageData['sub_category_id'] = isset($request->sub_category_id) ? $request->sub_category_id : 0;
        $this->pageData['refresh_filter'] = isset($request->r) ? $request->r : '';


        return view('admin.list', compact('html'))
            ->with(['pageData' => $this->pageData])
            ->with(['callFrom' => 'StoriesList'])
            ->with(['request' => $request])
            ->with(['multipleDelete' => $this->multipleDelete])
            ->with(['FormURL' => route("admin-stories-delete-multiple")])
            ->with(['selectedViewType' => ucfirst($this->selectedViewType)]);
    }

    public function multidelete(Request $request)
    {
        foreach ($request->delete as $item){
            try {
                $user = Story::find($item);
                $userID = isset($user->user_id) && !empty($user->user_id) ? $user->user_id : 0;
                if ($user->delete()) {
                    StoryStar::where("story_id", "=", $item)->delete();
                    Comment::where("story_id", "=", $item)->delete();
                    if (isset($userID) && !empty($userID))
                        Story::updateStoryCount($userID);
                } else {
                    $request->session()->flash('alert-danger', "We couldn't delete story with ID:".$item);
                    return redirect()->back();
                }
            } catch (\Exception $e) {
                $request->session()->flash('alert-danger', $e->getMessage());
                return redirect()->back();
            }
        }
        $request->session()->flash('alert-success', 'Successfully deleted '. count($request->delete) .' stories!');
        return redirect()->back();
    }


    public function clear()
    {
        Session::forget('input_filters');
        return redirect()->route('admin-stories-list');
    }

    public function index(Request $request)
    {
        $this->pageData['MainHeading'] = $this->singularName;

        if(count($request->all())==0){
            if (Session::has('input_filters')){
                if (count(Session::get('input_filters'))>1){
                    return redirect()->route('admin-stories-list', Session::get('input_filters'));
                }
            }
        }

        $story_count = Story::all()->count();
        
        $stories = Story::select('*');

        if (Input::get('order_by') == 'desc_votes') {
            $stories = $stories->withCount('nominatedstories')->with('theme')->join('story_ratings', 'story_ratings.story_id', '=', 'stories.story_id');
            $stories->orderBy('total_rate', 'DESC');
        } else if (Input::get('order_by') == 'asc_votes') {
            $stories = $stories->withCount('nominatedstories')->with('theme')->join('story_ratings', 'story_ratings.story_id', '=', 'stories.story_id');
            $stories->orderBy('total_rate', 'ASC');
        }

        if (Input::get('rating') !== null && Input::get('rating') !== '0') {
            $stories = $stories->withCount('nominatedstories')->with('theme')->join('story_ratings', 'story_ratings.story_id', '=', 'stories.story_id');
        } else {
            $stories = $stories->withCount('nominatedstories')->with('story_rating', 'theme');
        }

        if (Input::get('author') !== null) {
            $stories->where('author_name', 'like', '%' . Input::get('author') . '%');
        }

        if (Input::get('s') !== null) {
            $stories->where('story_title', 'like', '%' . Input::get('s') . '%');
        }

        if (Input::get('showCopies') == 'No') {
            $stories->groupBy('story_title');
        }

        if (Input::get('havingComments') == 'Yes') {
            $stories->where('comment_count', '>', 0);
        }

        if (Input::get('havingComments') == 'No') {
            $stories->where('comment_count', '=', 0)->orWhere('comment_count', '=', null);
        }

        if (Input::get('state') !== null) {
            $stories->where('author_address', 'like', '%' . Input::get('state') . '%');
        }

        if (Input::get('subcategory') !== null) {
            $stories->whereRaw('`stories`.`story_id` IN (SELECT `story_id` FROM `story_categories` WHERE `sub_category_id` = '.Input::get('subcategory').' )');
        }

        if (Input::get('category') !== null) {
            $stories->where('category_id', '=', Input::get('category'));
        }

        if (Input::get('country') !== null) {
            $stories->where('author_country', '=', Input::get('country'));
        }

        if (Input::get('gender') == 'Male') {
            $stories->where('author_gender', '=', 'Male');
        }

        if (Input::get('gender') == 'Female') {
            $stories->where('author_gender', '=', 'Female');
        }

        if (Input::get('gender') == 'Unspecified') {
            $stories->where('author_gender', '=', 'Unspecified')->orWhere('author_gender', '=', null);
        }

        if (Input::get('theme') !== null) {
            $stories->join('story_themes', function ($join) {
                $join->on('story_themes.story_id', '=', 'stories.story_id');
            });
            $stories->where('story_themes.theme_id', '=', Input::get('theme'));
        }

        if (Input::get('subject') !== null) {
            $stories->join('story_subjects', function ($join) {
                $join->on('story_subjects.story_id', '=', 'stories.story_id');
            });
            $stories->where('story_subjects.subject_id', '=', Input::get('subject'));
        }

        if (Input::get('story_id') !== null) {
            $stories->where('story_id', 'like', '%' . Input::get('story_id') . '%');
        }

        if (Input::get('story_id') !== null) {
            $stories->where('story_id', 'like', '%' . Input::get('story_id') . '%');
        }

        if (Input::get('order_by') !== null) {
            if (Input::get('rating') !== null) {
                if (Input::get('order_by') == 'desc_id') {
                    $stories->orderBy('stories.story_id', 'desc');
                }
                if (Input::get('order_by') == 'asc_id') {
                    $stories->orderBy('stories.story_id', 'asc');
                }
            } else {
                if (Input::get('order_by') == 'desc_id') {
                    $stories->orderBy('story_id', 'desc');
                }
                if (Input::get('order_by') == 'asc_id') {
                    $stories->orderBy('story_id', 'asc');
                }
            }

            if (Input::get('order_by') == 'desc_views') {
                $stories->orderBy('views', 'desc');
            }
            if (Input::get('order_by') == 'asc_views') {
                $stories->orderBy('views', 'asc');
            }

            if (Input::get('order_by') == 'desc_nomi') {
                $stories->orderBy('nominatedstories_count', 'desc');
            }
        } else {
            $stories->orderBy('stories.story_id', 'desc');
        }

        if (Input::get('data_range') !== null) {
            $stories->where('created_timestamp', '>', Carbon::now()->subDays(Input::get('data_range'))->timestamp);
        }

        if (Input::get('rating') == "45") {
            $stories->where('average_rate', '>', 4.5);
        }

        if (Input::get('rating') == "4") {
            $stories->where('average_rate', '>', 4.0)->where('average_rate', '<', 4.5);
        }

        if (Input::get('rating') == "34") {
            $stories->where('average_rate', '>', 3.0)->where('average_rate', '<', 4.0);
        }

        if (Input::get('rating') == "3") {
            $stories->where('average_rate', '<', 3.0);
        }

        if (Input::get('rating') == "0") {
            $stories->doesntHave('story_rating');
        }

        if (Input::get('author_age') !== null) {
            $stories->where('author_dob', '>', Carbon::now()->year - Input::get('author_age'));
        }

        if (Input::get('classic_novels') == 1) {
            $stories->where('sub_category_id', '!=', 177)->where('stories.theme_id', '!=', 41);
        }
        $stories = $stories->paginate(Input::get('paginator_length'));



        Session::put('input_filters',$request->all());
        return view('admin.list_new', compact('story_count', 'stories'))
            ->with(['pageData' => $this->pageData]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (request()->segment(2) == "novels") {
            $this->pageData['MainHeading'] = "Add " . $this->singularName1;
            $this->pageData['PageTitle'] = "Add " . $this->singularName1;
            $this->pageData['SubNav'] = "Add Novel";
            $this->pageData['NavHeading'] = "New " . $this->singularName1;
        } else {
            $this->pageData['MainHeading'] = "Add " . $this->singularName;
            $this->pageData['PageTitle'] = "Add " . $this->singularName;
            $this->pageData['SubNav'] = "Add Story";
            $this->pageData['NavHeading'] = "New " . $this->singularName;
        }
        // Add  App Form
        $form = $this->form();

        return view('admin.add')
            ->with(['pageData' => $this->pageData])
            ->with(compact('form'))
            ->with(compact('jsValidator'));
    }

    public function form($id = "", $callFrom = "")
    {

        $data = array();
        if (request()->segment(2) == "novels"):
            if ($id):
                $action = route('admin-novels-update', $id);
                $method = 'patch';
            else:
                $action = route('admin-novels-add');
                $method = 'post';
            endif;
        else:
            if ($id):
                $action = route('admin-stories-update', $id);
                $method = 'patch';
            else:
                $action = route('admin-stories-add');
                $method = 'post';
            endif;
        endif;
        $fmForm = new FmForm('StoryFrm', $action, $method, ['class' => 'smart-form', 'novalidate' => 'novalidate'], true);

        $fmForm->title('Add New ' . $this->singularName);
        $fmForm->saveText('Save');
        $fmForm->jsValidation($this->jsValidation);


        $self_story = "Yes";
        $disabled = [];

        $updatedAppFiled = [];
        // Get edit from data in case of edit record
        if ($id) {

            $data = Story::find($id);

            $data->the_story = strip_tags($data->the_story);


            if (!isset($data) || empty($data)) {
                return abort(404);
            }
            if ($data->subject_id == "177"):
                $fmForm->title('Update ' . $this->singularName1);
            else:
                $fmForm->title('Update ' . $this->singularName);
            endif;
            $fmForm->saveText('Update');

            $updatedFields = [
                "tooltip" => "You can't change the Field."
            ];


            if ($data->self_story == 1)
                $self_story = "No";
            else
                $self_story = "Yes";


            if ($self_story == "Yes")
                $disabled = ["disabled" => "disabled"];

            //->where("user_id", "=", $data->user_id)
            $user = SiteUser::select(\DB::raw("CONCAT(name, ' - [',email,']') AS name"), 'user_id')->orderBy("name", "asc")->where("is_author", "=", 1)->get()->toArray();
            $user = array_combine(array_column($user, 'user_id'), array_column($user, 'name'));


            /// dd($user);

        } else {

            $disabled = ["disabled" => "disabled"];
            $user = SiteUser::select(\DB::raw("CONCAT(name, ' - [',email,']') AS name"), 'user_id')->orderBy("name", "asc")->where("is_author", "=", 1)->get()->toArray();
            $user = array_combine(array_column($user, 'user_id'), array_column($user, 'name'));

        }


        $Themes = Theme::orderBy("theme_order", "asc")->get()->toArray();
        $Themes = array_combine(array_column($Themes, 'theme_id'), array_column($Themes, 'theme_title'));

        /*if(request()->segment(2)=="novels"):
            $Subjects = Subject::where('subject_id','=','177')->orderBy("subject_title", "asc")->get()->toArray();
        else:*/
        //$Subjects = Subject::where('subject_id','<>','177')->orderBy("subject_title", "asc")->get()->toArray();
        $Subjects = Subject::orderBy("subject_title", "asc")->get()->toArray();
        //endif;
        $Subjects = array_combine(array_column($Subjects, 'subject_id'), array_column($Subjects, 'subject_title'));


        $mCategories = [];
        $mCategories = StoryCategory::where("story_id", "=", "$id")->whereNull('sub_category_id')->get()->toArray();

        if ($mCategories) {
            $mCategories = array_column($mCategories, 'category_id');
            /* $uCategory = isset($data->category_id) ? $data->category_id : "";
             if ($uCategory)
                 array_push($mCategories, $uCategory);
             $mCategories = array_unique($mCategories);*/
        }

        $sCategories = [];
        $sCategories = StoryCategory::where("story_id", "=", "$id")->whereNull('category_id')->get()->toArray();

        if ($sCategories) {
            $sCategories = array_column($sCategories, 'sub_category_id');
        }


        $sSubjects = [];
        $sSubjects = StorySubject::where("story_id", "=", "$id")->get()->toArray();
        if ($sSubjects) {
            $sSubjects = array_column($sSubjects, 'subject_id');
        }

        $sThemes = [];
        $sThemes = StoryTheme::where("story_id", "=", "$id")->get()->toArray();
        if ($sThemes) {
            $sThemes = array_column($sThemes, 'theme_id');
        }

        $ageGroup = ["Child" => "CHILD", "Teen" => "TEEN", "Adult" => "ADULT"];
        if (request()->segment(2) == "novels" || (isset($data->subject_id) && $data->subject_id == 177)):
            $text = "Novel";
            $type = "hidden";
            $value = 177;
        else:
            $text = "Story";
            $type = "select";
            $value = (isset($data->subject_id) ? $data->subject_id : "");
        endif;

        $fmForm
            ->add(array(
                    "col" => 12,
                    "type" => "html",
                    "html" => "Select Author of " . $text,
                )
            )
            ->add(array(
                "col" => 12,
                "type" => "select",
                "options" => $user,
                "name" => "user_id",
                "label" => "Author",
                "value" => (isset($data->user_id) ? $data->user_id : ""),
                "attr" => array_merge(['style' => 'width:100%', 'class' => 'select2'], $disabled)
            ))->add(array(
                    "col" => 12,
                    "type" => "html",
                    "html" => "OR",
                )
            )
            ->add(array(
                "col" => 12,
                "type" => "checkbox-toggle",
                "name" => "self_story",
                "label" => "Are you posting this " . $text . " on behalf of another author?",
                "value" => $self_story,
            ))
            ->add(array(
                    "parent-class" => isset($self_story) && $self_story == "Yes" ? "parent_class" : "hide parent_class ",
                    "col" => 3,
                    "type" => "text",
                    "name" => "author_name",
                    "label" => "Author Name",
                    "value" => (isset($data->author_name) ? $data->author_name : ""),
                )
            )
            ->add(array(
                "parent-class" => isset($self_story) && $self_story == "Yes" ? "parent_class" : "hide parent_class ",
                "col" => 3,
                "type" => "text",
                "name" => "author_address",
                "label" => "Author Address",
                "value" => (isset($data->author_address) ? $data->author_address : ""),

            ))
            ->add(array(
                "parent-class" => isset($self_story) && $self_story == "Yes" ? "parent_class" : "hide parent_class ",
                "col" => 2,
                "type" => "select",
                "options" => getCountries(),
                "name" => "author_country",
                "label" => "Author Country",
                "value" => (isset($data->author_country) ? $data->author_country : ""),
                "attr" => ['style' => 'width:100%', 'class' => 'form-control']
            ))
            ->add(array(
                "parent-class" => isset($self_story) && $self_story == "Yes" ? "parent_class" : "hide parent_class ",
                "col" => 2,
                "type" => "select",
                "options" => getGender(),
                "name" => "author_gender",
                "label" => "Author Gender",
                "value" => (isset($data->author_gender) ? $data->author_gender : ""),
                "attr" => ['style' => 'width:100%', 'class' => 'form-control']
            ))
            ->add(array(
                "parent-class" => isset($self_story) && $self_story == "Yes" ? "parent_class" : "hide parent_class ",
                "col" => 2,
                "type" => "select",
                "options" => getYears(),
                "name" => "author_dob",
                "label" => "Author DOB",
                "value" => (isset($data->author_dob) ? $data->author_dob : ""),
                "attr" => ['style' => 'width:100%', 'class' => 'form-control']
            ))
            ->add(array(
                    "col" => 12,
                    "type" => "html",
                    "html" => "<hr/>",
                )
            )
            ->add(array(
                    "col" => 12,
                    "type" => "html",
                    "html" => $text . " Detail",
                )
            )
            ->add(array(
                    "col" => 12,
                    "type" => "text",
                    "name" => "story_title",
                    // "label" => "Title (max 40 characters)",
                    "label" => "Title",
                    "value" => (isset($data->story_title) ? $data->story_title : ""),
                    //  "attr" => ['maxlength' => '40']
                )
            )
            ->add(array(
                    "col" => 12,
                    "type" => "hidden",
                    "name" => "callFrom",
                    "label" => "CallFROM",
                    "value" => (isset($callFrom) ? $callFrom : ""),

                )
            )
            ->add(array(
                    "col" => 12,
                    "type" => "text",
                    "name" => "short_description",
                    //"label" => "Short Description (max 250 characters)",
                    "label" => "Short Description",
                    "value" => (isset($data->short_description) ? $data->short_description : ""),
                    // "attr" => ['maxlength' => '250']
                )
            )
            ->add(array(
                "type" => "select",
                "options" => $Themes,
                "name" => "theme_id",
                "label" => "Choose Theme",
                "value" => (isset($data->theme_id) ? $data->theme_id : ""),
                "attr" => ['style' => 'width:100%', 'class' => 'form-control'],
            ))
            ->add(array(
                    "type" => $type,
                    "options" => $Subjects,
                    "name" => "subject_id",
                    "label" => "Choose Subject",
                    "value" => $value,
                    "attr" => ['style' => 'width:100%', 'class' => 'form-control'],
                )
            )
            ->add(array(
                    "col" => "4",
                    "type" => "select",
                    "options" => $ageGroup,
                    "name" => "written_by",
                    "label" => "Choose Author's Age Group ",
                    "value" => isset($data->written_by) ? $data->written_by : "",
                    "attr" => ['style' => 'width:100%', 'class' => 'form-control', 'name' => 'written_by'],
                )
            )->add(array(
                    "col" => "4",
                    "type" => "select",
                    "options" => getCategories(),
                    "name" => "category_id",
                    "label" => "Choose Category",
                    "value" => isset($data->category_id) ? $data->category_id : "",
                    "attr" => ['style' => 'width:100%', 'class' => 'form-control', 'name' => 'category_id'],
                )
            )
            ->add(array(
                    "col" => "4",
                    "type" => "select",
                    "options" => getSubCategories(),
                    "name" => "sub_category_id",
                    "label" => "Choose Sub Category",
                    "value" => isset($data->sub_category_id) ? $data->sub_category_id : "",
                    "attr" => ['style' => 'width:100%', 'class' => 'form-control', 'name' => 'sub_category_id'],
                )
            )
            ->add(array(
                    "col" => 12,
                    "type" => "textarea",
                    "name" => "the_story",
                    "label" => "Story",
                    "text" => $text,
                    "value" => (isset($data->the_story) ? $data->the_story : ""),
                    // "attr" => ["maxlength" => 50000, "onkeyup" => "countChar(this,50000,'the_story')"]


                )
            );


        if ($callFrom == "flag") {
            $fmForm->add(array(
                    "type" => "select",
                    "options" => ['Active' => 'Active', 'Inactive' => 'Inactive'],
                    "name" => "status",
                    "label" => "Status",
                    "value" => (isset($data['status']) ? $data['status'] : ""),
                    "attr" => ['style' => 'width:100%', 'class' => 'form-control']
                )
            );

        } else {
            $fmForm->add(array(
                    "col" => 12,
                    "type" => "hidden",
                    "name" => "status",
                    "label" => "status",
                    "value" => "Active",
                )
            );
        }


        $fmForm->add(array(
                "col" => (isset($data['image']) ? 5 : 6),
                "type" => "file",
                "name" => "image",
                "label" => "",
                "value" => (isset($data['image']) ? getStoryImg($data['image']) : "")
            )
        )
            ->add(array(
                    "col" => 12,
                    "type" => "html",
                    "html" => "<hr/>",
                )
            )
            ->add(array(
                    "col" => 12,
                    "type" => "html",
                    "html" => "Add In Multiple Categories",
                )
            )
            /*->add(array(
                    "type" => "select",
                    "options" => getCategories(),
                    "name" => "multiple_category_id",
                    "label" => "Choose Multiple Categories",
                    "value" => $mCategories,
                    "attr" => ['style' => 'width:100%', 'class' => 'select2', 'multiple' => true, 'name' => 'multiple_category_id[]'],
                )
            )*/
            ->add(array(
                    "col" => 12,
                    "type" => "select",
                    "options" => getSubCategories(),
                    "name" => "multiple_sub_category_id",
                    "label" => "Choose Multiple Subcategories",
                    "value" => $sCategories,
                    "attr" => ['style' => 'width:100%', 'class' => 'select2', 'multiple' => true, 'name' => 'multiple_sub_category_id[]'],
                )
            )
            ->add(array(

                    "type" => "select",
                    "options" => $Themes,
                    "name" => "multiple_theme_id",
                    "label" => "Choose Multiple Themes",
                    "value" => $sThemes,
                    "attr" => ['style' => 'width:100%', 'class' => 'select2', 'multiple' => true, 'name' => 'multiple_theme_id[]'],
                )
            )
            ->add(array(

                    "type" => "select",
                    "options" => $Subjects,
                    "name" => "multiple_subject_id",
                    "label" => "Choose Multiple Subjects",
                    "value" => $sSubjects,
                    "attr" => ['style' => 'width:100%', 'class' => 'select2', 'multiple' => true, 'name' => 'multiple_subject_id[]'],
                )
            );


        return $fmForm->getForm();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoryRequest $request)
    {

        $image = "";
        // File upload here
        $destinationPath = storage_path("story");
        $file = $request->file('image');
        if ($file) {
            //Move Uploaded File
            $image = "story_" . NewGuid() . "." . $file->guessExtension();
            $file->move($destinationPath, $image);
        }


        $Author = SiteUser::find($request['user_id']);

        try {

            $otherAuthorStoryData = [];

            if (isset($request['self_story']) && $request['self_story'] == "Yes") {

                $Author = SiteUser::firstOrCreate([
                    'name' => $request['author_name'],
                    'country' => $request['author_country'],
                    'address' => $request['author_address'],
                ], [
                    'gender' => $request['author_gender'],
                    'dob' => $request['author_dob'],
                    'password' => bcrypt(str_random(8)),
                    'email' => time() . '@storystar.com',
                    'created_timestamp' => time(),
                    'updated_timestamp' => time(),
                    'verify_token' => str_random(40),
                    'active' => 1,
                    'is_author' => 1,
                    'is_profile_complete' => 1
                ]);

                $otherAuthorStoryData['author_name'] = $request['author_name'];
                $otherAuthorStoryData['author_country'] = $request['author_country'];
                $otherAuthorStoryData['author_gender'] = $request['author_gender'];
                $otherAuthorStoryData['author_dob'] = $request['author_dob'];
                $otherAuthorStoryData['author_address'] = $request['author_address'];
                $otherAuthorStoryData['user_id'] = $Author->user_id;
                $otherAuthorStoryData['self_story'] = 0;
            } else {
                $otherAuthorStoryData['author_name'] = $Author->name;
                $otherAuthorStoryData['author_country'] = $Author->country;
                $otherAuthorStoryData['author_gender'] = $Author->gender;
                $otherAuthorStoryData['author_dob'] = $Author->dob;
                $otherAuthorStoryData['author_address'] = $Author->address;
                $otherAuthorStoryData['user_id'] = $request['user_id'];
                $otherAuthorStoryData['self_story'] = 1;
            }


            $data = Story::create(
                array_merge(
                    [
                        'story_title' => strip_tags($request['story_title']),
                        'short_description' => strip_tags($request['short_description']),
                        'theme_id' => $request['theme_id'],
                        'subject_id' => $request['subject_id'],
                        'category_id' => $request['category_id'],
                        'sub_category_id' => $request['sub_category_id'],
                        'the_story' => strip_tags($request['the_story']),
                        'written_by' => $request['written_by'],
                        'story_code' => '',
                        'image' => $image,
                        'status' => $request['status'],
                        'created_timestamp' => time(),
                        'updated_timestamp' => time(),
                    ], $otherAuthorStoryData
                )
            );

            if ($data->story_id) {


                // For Multiple Categories
                $StoryCategory = new StoryCategory();
                $results = $StoryCategory->updateMultipleCategories($request, $data->story_id);


                $request->session()->flash('alert-success', $this->singularName . ' has been added successfully!');
            } else {
                $request->session()->flash('alert-danger', 'There is some issue.Please try again!');
            }
        } catch (\Exception $e) {
            $request->session()->flash('alert-danger', $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (request()->segment(2) == "novels"):
            $text = "Novel";
            $this->pageData['PageTitle'] = $this->singularName1 . " Detail";
            $this->pageData['SubNav'] = "Add " . $this->singularName1;
            $this->pageData['MainHeading'] = $this->singularName1 . " Detail";
        else:
            $text = "Story";
            $this->pageData['PageTitle'] = $this->singularName . " Detail";
            $this->pageData['SubNav'] = "Add " . $this->singularName;
            $this->pageData['MainHeading'] = $this->singularName . " Detail";
        endif;
        $detailData = array();
        $storyData = Story::select('stories.*')->
        leftJoin('users', 'users.user_id', '=', 'stories.user_id')
            ->leftJoin('story_ratings', 'story_ratings.story_id', '=', 'stories.story_id')
            ->with('theme')
            ->with('subject')
            ->with('category')
            ->with('rate')
            ->with('subcategory')
            ->Find($id);


        if (!isset($storyData) || empty($storyData)) {
            return abort(404);
        }
        $detailData['Image'] = getStoryImage($storyData->image);
        $detailData['ID'] = $storyData->story_id;
        $detailData['Author Name'] = $storyData->author_name;
        $detailData['Title'] = decodeStr($storyData->story_title);
        $detailData['Short Description'] = decodeStr($storyData->short_description);
        $detailData['Status'] = $storyData->status;
        $detailData['Theme'] = $storyData->theme->theme_title;
        $detailData['Subject'] = $storyData->subject->subject_title;
        $detailData['Category'] = $storyData->category->category_title;
        $detailData['Sub Category'] = $storyData->subcategory->sub_category_title;
        $detailData['Created Date Time'] = my_date($storyData->created_timestamp, '', '');
        $detailData['Updated Date Time'] = my_date($storyData->updated_timestamp, '', '');
        $detailData['Story'] = html_entity_decode($storyData->the_story);
        $detailData['Author Self Story'] = isset($storyData->self_story) ? "Yes" : "No";
        $detailData['Author Country'] = $storyData->author_country;
        $detailData['Author Gender'] = $storyData->author_gender;
        $detailData['Author DOB'] = $storyData->author_dob;

        $detailData['Author Address'] = $storyData->author_address;


        //isset($storyData->rate->average_rate) && $storyData->rate->average_rate >= 4 && $storyData->views >= 100 &&
        if ($storyData->theme_id != 41) {
            $actions['Set Story Of Day'] = '<a href="' . route('admin-story-star-addfromstories', ['story_id' => $storyData->story_id, 'type' => 'day']) . '" class="btn bg-color-orange txt-color-white"><i class="glyphicon glyphicon-star"></i> Set ' . $text . ' Of Day</a>';
            $actions['Set Story of Week'] = '<a href="' . route('admin-story-star-addfromstories', ['story_id' => $storyData->story_id, 'type' => 'week']) . '" class="btn bg-color-green txt-color-white"><i class="glyphicon glyphicon-star"></i> Set ' . $text . ' of Week</a>';
        }

        $deleteURL = route('admin-stories-delete', $storyData->story_id);
        $blockUserURL = route('admin-site-member-block', ['user_id' => $storyData->user_id]);


        $actions['Delete Story'] = '<a href="javascript:void(0)"  class="btn btn-x btn-danger txt-color-white" onclick="confirmBox(\'' . $deleteURL . '\')" rel="tooltip" data-placement="top" data-original-title="Delete"><i class="glyphicon glyphicon-remove" ></i> Delete ' . $text . ' </a>';
        $actions['Edit Story'] = '<a href="' . route('admin-stories-edit', ['story_id' => $storyData->story_id]) . '" class="btn btn-primary txt-color-white"><i class="glyphicon glyphicon-edit"></i> Edit ' . $text . '</a>';
        $actions['Front-end Link'] = '  <a target="_blank" href="' . route('app-story', $storyData->story_id) . '" class="btn bg-color-orangeDark txt-color-white" rel="tooltip" data-placement="top" data-original-title=""><i class="fa fa-external-link "></i> View ' . $text . ' at Front-end</a>';


        $blockTooltip = "";
        if ($storyData->self_story == 1) {
            if ($storyData->is_blocked == 1) {
                $blockTooltip = "Unblock User";
            } else {
                $blockTooltip = "Block User";
            }

            $actions['Block User'] = '<a href="javascript:void(0)"  class="btn bg-color-red  txt-color-white" onclick="confirmBoxOnBlock(\'' . $blockUserURL . '\')" ><i class="fa fa-info-circle" ></i> ' . $blockTooltip . '</a>';

        }


        $story = Story::select('stories.*')->with("favstories")
            // users join
            ->leftJoin('users', function ($join) {
                $join->on('users.user_id', '=', 'stories.user_id')
                    ->whereNull('users.deleted_at')
                    ->where('users.active', "=", 1);
            })
            //  ->where("status", "=", "Active")
            ->find($id);

        return view('admin.storydetail')
            ->with(['pageData' => $this->pageData])
            ->with(compact('detailData'))
            ->with(compact('actions'))
            ->with(compact('story'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Builder $builder, Request $request, $id, $callFrom = '')
    {
        $subject = Story::select("subject_id")->where("story_id", $id)->first();
        //var_dump($subject);
        //var_dump($subject->subject_id);exit;
        if ($subject->subject_id == 177):
            $text = "novels";
            $this->pageData['MainHeading'] = "Edit " . $this->singularName1;
            $this->pageData['PageTitle'] = "Edit " . $this->singularName1;
            $this->pageData['SubNav'] = "Edit Novel";
            $this->pageData['NavHeading'] = "Edit " . $this->singularName1;
            /* List UI Generator */
            $this->pageData['MainHeading'] = $this->singularName1;
        else:
            $text = "stories";
            $this->pageData['MainHeading'] = "Edit " . $this->singularName;
            $this->pageData['PageTitle'] = "Edit " . $this->singularName;
            $this->pageData['SubNav'] = "Edit Story";
            $this->pageData['NavHeading'] = "Edit " . $this->singularName;
            /* List UI Generator */
            $this->pageData['MainHeading'] = $this->singularName;
        endif;

        $form = $this->form($id, $callFrom);

        $story_id = $id;
        $this->storyID = $request->story_id;

        /* Set and Get list view for this page*/
        if ($this->selectedViewType)
            $this->selectedViewType = $this->listView($request);

        /* List View Data */
        if (request()->ajax()) {
            return $this->getAjaxData($request);
        }

        /* List UI Generator */
        $this->pageData['MainHeading'] = $this->singularName;


        // List Columns
        $viewColumns = [
            ['data' => 'comment_id', 'name' => 'comment_id', 'title' => 'ID', 'searchable' => true, 'class' => 'text-center', 'width' => 50],
            ['data' => 'name', 'name' => 'name', 'title' => 'Commented By', 'data-class' => 'expand', 'width' => 150],
            ['data' => 'comment', 'name' => 'comment', 'title' => 'Comment', 'data-class' => 'expand'],
            ['data' => 'commented_at', 'name' => 'commented_at', 'title' => 'Commented At', 'data-class' => 'expand', 'width' => 150],
            ['defaultContent' => '', 'data' => 'action', 'name' => 'action', 'title' => 'Action', 'render' => '', 'orderable' => false, 'searchable' => false, 'exportable' => false, 'printable' => false, 'footer' => '', 'class' => 'text-center', 'width' => '120px']
        ];

        $html = $this->getDataTable($builder, "Comment_TBL", $viewColumns, 'Avatar', 'admin-stories-edit', [[0, "desc"]]);


        return view('admin.add', compact('html'))
            ->with(['pageData' => $this->pageData])
            ->with(compact('form'))
            ->with(compact('story_id'))
            ->with(compact('jsValidator'))
            ->with(['selectedViewType' => ucfirst($this->selectedViewType)]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(StoryRequest $request, $id)
    {


        // Update Backend User
        try {
            $updateRecord = Story::find($id);

            $updateRecord->story_title = strip_tags($request->story_title);
            $updateRecord->short_description = strip_tags($request->short_description);
            $updateRecord->the_story = strip_tags($request->the_story);
            $updateRecord->theme_id = $request->theme_id;
            $updateRecord->subject_id = $request->subject_id;
            $updateRecord->written_by = $request->written_by;
            $updateRecord->category_id = $request->category_id;
            $updateRecord->sub_category_id = $request->sub_category_id;
            $updateRecord->status = $request->status;
            $updateRecord->updated_timestamp = time();


            if ($request->self_story == "Yes") {

                $updateRecord->author_name = $request->author_name;
                $updateRecord->author_country = $request->author_country;
                $updateRecord->author_gender = $request->author_gender;
                $updateRecord->author_dob = $request->author_dob;
                $updateRecord->author_address = $request->author_address;
                $updateRecord->user_id = 0;
                $updateRecord->self_story = 0;
            } else {

                $Author = SiteUser::find($request->user_id);

                $updateRecord->author_name = $Author->name;
                $updateRecord->author_country = $Author->country;
                $updateRecord->author_gender = $Author->gender;
                $updateRecord->author_dob = $Author->dob;
                $updateRecord->author_address = $Author->address;
                $updateRecord->user_id = $request->user_id;
                $updateRecord->self_story = 1;


            }


            // File upload here
            $destinationPath = storage_path("story");
            $file = $request->file('image');
            if ($file) {
                //Move Uploaded File
                $image = "story_" . NewGuid() . "." . $file->guessExtension();
                $file->move($destinationPath, $image);
                $updateRecord->image = $image;
            }

            if ($updateRecord->save()) {

                $StoryCategory = new StoryCategory();
                $results = $StoryCategory->updateMultipleCategories($request, $id);

                // Delete Flagged Story From Table
                if ($updateRecord->status == "Active")
                    Flag::where("story_id", "=", "$id")->delete();


                $request->session()->flash('alert-success', $this->singularName . ' has been added successfully!');

            } else {
                $request->session()->flash('alert-danger', 'There is some issue.Please try again!');
            }
        } catch (\Exception $e) {
            $request->session()->flash('alert-danger', $e->getMessage());
        }


        if (isset($request->callFrom) && $request->callFrom == "flag")
            return redirect()->route("admin-flag-list");
        else if (isset($request->callFrom) && $request->callFrom == "filtered")
            return redirect()->route("admin-story-star-list");
        else
            return redirect()->route("admin-stories-list");

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {

        // Soft Delete Method
        try {
            $user = Story::find($id);
            $userID = isset($user->user_id) && !empty($user->user_id) ? $user->user_id : 0;
            if ($user->delete()) {
                StoryStar::where("story_id", "=", "$id")->delete();
                Comment::where("story_id", "=", "$id")->delete();
                // Update Story Count in users table
                if (isset($userID) && !empty($userID))
                    Story::updateStoryCount($userID);
                $request->session()->flash('alert-success', $this->singularName . ' has been deleted successfully!');
            } else {
                $request->session()->flash('alert-danger', 'There is some issue.Please try again!');
            }
        } catch (\Exception $e) {
            $request->session()->flash('alert-danger', $e->getMessage());
        }
        return redirect()->back();
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroyMany(Request $request)
    {

        // Soft Delete Method
        try {
            $id = $request->id;


            $allAuthors = [];
            $allAuthors = Story::whereIn("story_id", $id)->select("user_id")->get()->toArray();
            $allAuthors = array_column($allAuthors, "user_id");


            if (Story::whereIn("story_id", $id)->delete()) {


                foreach ($allAuthors as $i) {
                    Story::updateStoryCount($i);
                }


                Comment::whereIn("story_id", $id)->delete();
                StoryStar::whereIn("story_id", $id)->delete();

                $request->session()->flash('alert-success', $this->pluralName . ' has been deleted successfully!');
            } else {
                $request->session()->flash('alert-danger', 'There is some issue.Please try again!');
            }

        } catch (\Exception $e) {
            $request->session()->flash('alert-danger', $e->getMessage());

        }

        $request->session()->flash('alert-success', $this->pluralName . ' has been deleted successfully!');
        return redirect()->back();


    }


    public function getAjaxData($request)
    {
        $apps = Story::withCount('comment')->whereNull("stories.deleted_at")->select(
            [
                'stories.*',
                \DB::Raw('COUNT(nominated_stories.id) as story_nominations'),
                'users.name',
                'users.email',
                'story_ratings.average_rate',
                'story_ratings.total_rate',
                'themes_list.theme_title',
                'subjects_list.subject_title',
                'category_list.category_title',

            ]
        );

        $apps = $apps->leftJoin('users', 'users.user_id', '=', 'stories.user_id');
        $apps = $apps->leftJoin('story_ratings', 'story_ratings.story_id', '=', 'stories.story_id');
        $apps = $apps->leftJoin('nominated_stories', 'stories.story_id', '=', 'nominated_stories.story_id');
        $apps = $apps->join('themes_list', 'themes_list.theme_id', '=', 'stories.theme_id');
        $apps = $apps->join('subjects_list', 'subjects_list.subject_id', '=', 'stories.subject_id');
        $apps = $apps->join('category_list', 'category_list.category_id', '=', 'stories.category_id');
        $apps = $apps->groupBy('stories.story_id');
        $apps = $apps->whereNull('users.deleted_at');


        //dd($apps->first()->comment->count());

        //  $apps = $apps->where('users.active', "=", 1);
        //  $apps = $apps->where('users.active', "=", 1);
        // $apps = $apps->where('stories.theme_id', "!=", 41);

        if (isset($request->toArray()['filter']) && !empty($request->toArray()['filter'])) {
            $this->data = [];
            $this->data = (json_decode($request->toArray()['filter'], true)[0]);

            if (isset($this->data['subcategory']) && !empty($this->data['subcategory'])) {
                $apps = $apps->join('story_categories As story_categories2', function ($join) {
                    $join->on('story_categories2.story_id', '=', 'stories.story_id')
                        ->whereNull('story_categories2.category_id');
                });
            }

            $apps = $apps->where(function ($query) {


                // Author Filter
                if (isset($this->data['author']) && !empty($this->data['author'])) {

                    $this->data['author'] = trim($this->data['author']);

                    $query->where('author_name', 'like', "%" . $this->data['author'] . "%");

                }

                if (isset($this->data['showCopies']) && $this->data['showCopies'] == "No") {
                    $query->groupBy('stories.story_title');
                    $query->groupBy('stories.author_name');
                }

                // Search For KeyWord In Story Title
                if ($this->data['s']) {
                    $this->data['s'] = trim($this->data['s']);
                    $query->where("story_title", "like", "%" . $this->data['s'] . "%");
                    $query->orWhere("short_description", "like", "%" . $this->data['s'] . "%");
                }

                // Theme Filter
                if ($this->data['theme']) {
                    $query->where("stories.theme_id", "=", $this->data['theme']);
                }

                // Subject Filter
                if ($this->data['subject']) {
                    $query->where("stories.subject_id", "=", $this->data['subject']);
                }

                // State Filter
                if (isset($this->data['state']) && !empty($this->data['state'])) {

                    $this->data['state'] = trim($this->data['state']);
                    $query->where('users.address', 'like', $this->data['state'] . "%");
                }

                // Country Filter
                if (isset($this->data['country']) && !empty($this->data['country'])) {
                    $query->where('users.country', 'like', $this->data['country'] . "%");
                }

                // Gender Filter
                if (isset($this->data['gender']) && !empty($this->data['gender'])) {
                    $query->where('users.gender', 'like', $this->data['gender'] . "%");
                }

                // Category Filter
                if ($this->data['category']) {
                    //$query->where("story_categories.category_id", "=", $this->data['category']);
                    $query->where("stories.category_id", "=", $this->data['category']);
                }

                // Sub Category Filter
                if (isset($this->data['subcategory']) && !empty($this->data['subcategory'])) {
                    $query->where("story_categories2.sub_category_id", "=", $this->data['subcategory']);

                }


                if (isset($this->data['havingComments']) && !empty($this->data['havingComments'])) {
                    if ($this->data['havingComments'] == "Yes")
                        $query->where("stories.comment_count", ">", "0");
                    elseif ($this->data['havingComments'] == "No")
                        $query->whereNull("stories.comment_count");
                }
            });
        }

        $table = DataTables::of($apps);

        $table->addColumn('commentcount', function ($apps) {
            return $apps->comment->count();
        });

        // Action Coloumn
        $table->addColumn('action', function ($apps) {

            $storyStar = "";
            $storyComments = "";
            $deleteURL = route('admin-stories-delete', $apps->story_id);
            if (request()->segment(2) == "novels" || (isset($apps->subject_id) && $apps->subject_id == 177)):
                //if($apps->subject_id=="177"):
                $text = "novels";
            else:
                $text = "stories";
            endif;

            if (isset($apps->comment_count) && !empty($apps->comment_count))
                $storyComments = '<a href="' . route('admin-stories-edit', $apps->story_id) . '#comments" class="btn btn-xs bg-color-yellow txt-color-white" rel="tooltip" data-placement="top" data-original-title=""><i class="glyphicon glyphicon-th-list"></i> Manage Comments</a>';

            //$apps->average_rate >= 4 && $apps->views >= 100 &&
            //&& $apps->average_rate >= 4
            if ($apps->theme_id != 41) {
                $storyStar = '<a href="' . route('admin-story-star-addfromstories', ['story_id' => $apps->story_id, 'type' => 'day']) . '" class="btn btn-xs bg-color-orange txt-color-white"" rel="tooltip" data-placement="top" data-original-title=""><i class="glyphicon glyphicon-star"></i> Add ' . $text . ' Of Day </a>';
                $storyStar .= ' <a href="' . route('admin-story-star-addfromstories', ['story_id' => $apps->story_id, 'type' => 'week']) . '" class="btn btn-xs  bg-color-green txt-color-white"" rel="tooltip" data-placement="top" data-original-title=""><i class="glyphicon glyphicon-star"></i> Add ' . $text . ' of Week</a>';


                $storyStar .= ' <a href="' . route('admin-story-star-addfromstories', ['story_id' => $apps->story_id, 'type' => $apps->category_id == 1 ? 'non' : 'fic']) . '" class="btn btn-xs  bg-color-blueDark txt-color-white"" rel="tooltip" data-placement="top" data-original-title=""><i class="glyphicon glyphicon-star"></i> ' . ($apps->category_id == 1 ? 'True life story of the week' : 'Fiction story of the week') . '</a>';
            }


            return '<a href="' . route('admin-stories-edit', $apps->story_id) . '" class="btn btn-xs btn-primary" rel="tooltip" data-placement="top" data-original-title=""><i class="glyphicon glyphicon-edit"></i> Edit</a>
                    <a href="' . route('admin-' . $text . '-detail', $apps->story_id) . '" class="btn btn-xs bg-color-pink txt-color-white" rel="tooltip" data-placement="top" data-original-title=""><i class="glyphicon glyphicon-th-list"></i> Detail</a>
                    <a href="javascript:void(0)"  class="btn btn-xs btn-danger txt-color-white" onclick="confirmBox(\'' . $deleteURL . '\')" rel="tooltip" data-placement="top" data-original-title=""><i class="glyphicon glyphicon-remove" ></i> Delete</a>
                    ' . $storyComments . $storyStar;
        });

        $table->editColumn('story_title', function ($apps) {

            $rank = "";
            $categoriesText = "";
            $title = ' <a href="' . route('admin-stories-detail', $apps->story_id) . '">' . $apps->story_title . '</a>';

            $floorRank = round(($apps->average_rate * 2)) / 2;
            $rankNo = ceil(number_format($apps->average_rate, 1));


            // Categories
            /* $categories = []; $categories = StoryCategory::where("story_id", "=", "$apps->story_id")->whereNull('sub_category_id')
                 ->join("category_list", "category_list.category_id", "=", "story_categories.category_id")
                 ->get()->toArray()

            if ($categories)
                $categories = array_column($categories, 'category_title');*/;


            $subjects = [];
            $subjects = StorySubject::select(\DB::raw('group_concat(" ",subjects_list.subject_title) as subjects'))
                ->where("story_id", "=", "$apps->story_id")
                ->join("subjects_list", "subjects_list.subject_id", "=", "story_subjects.subject_id")
                ->get()->toArray();
            if ($subjects)
                $subjects = isset($subjects[0]['subjects']) ? $subjects[0]['subjects'] : '';

            $themes = [];
            $themes = StoryTheme::select(\DB::raw('group_concat(" ",themes_list.theme_title) as themes'))
                ->where("story_id", "=", "$apps->story_id")
                ->join("themes_list", "themes_list.theme_id", "=", "story_themes.theme_id")
                ->get()->toArray();
            if ($themes)
                $themes = isset($themes[0]['themes']) ? $themes[0]['themes'] : '';


            // Sub Categories
            $subCategories = [];
            $subCategories = StoryCategory::where("story_id", "=", "$apps->story_id")->whereNull('category_id')
                ->join("sub_category_list", "sub_category_list.sub_category_id", "=", "story_categories.sub_category_id")
                ->get()->toArray();

            if ($subCategories)
                $subCategories = array_column($subCategories, 'sub_category_title');


            for ($i = 1; $i <= $floorRank; $i++) {
                $rank .= '<i class="fa fa-star fa-2x text-primary" style="color: #47588F"></i>';
            }

            foreach ($subCategories as $subCat) {
                $categoriesText .= "<br/>This story is listed as: $apps->category_title For $subCat / <span style='color: #47588F'>Theme:</span> $themes / <span style='color: #47588F'>Subject:</span> $subjects";
            }


            $str = "<b>" . $title . "</b> by  <span style='color: #47588F'>" . ucfirst($apps->author_name) . " </span><br/>";
            $str .= '<div class="product-deatil" style="padding: 5px 0 5px 0;">' . $rank . '
                     <span class="fa fa-2x"><h5>(' . number_format($apps->average_rate, 1) . ') Rank</h5></span>
                     </div>';


            $apps->total_rate = isset($apps->total_rate) ? $apps->total_rate : 0;

            $str .= "Post date: " . my_date($apps->created_timestamp, "Y-m-d") . " / Views: $apps->views / Votes: $apps->total_rate  / Country: $apps->author_country";

            if (isset($apps->comment_count) && !empty($apps->comment_count))
                $str .= " / Comments: " . $apps->comment_count;


            // $str .= "<br/><br/>This story is listed as: Fiction For Teens / Theme: $apps->theme_title / Subject: $apps->subject_title";
            //  $str .= "<br/>This story is listed as: Fiction For Teens / Theme: $apps->theme_title / Subject: $apps->subject_title" . "<br/><br/>";

            $str .= "<br/>" . $categoriesText . "<br/><br/>";
            $str .= decodeStr($apps->short_description);

            return decodeStr($str);


        });

        $table->editColumn('short_description', function ($apps) {
            return decodeStr($apps->short_description);
        });

        $table->filterColumn('created_timestamp', function ($query, $keyword) {
            $query->whereRaw("DATE_FORMAT(FROM_UNIXTIME(created_timestamp),' % d -%m -%Y % h:%i:%s') like ?", ["%$keyword%"]);
        });
        $table->editColumn('updated_timestamp', function ($apps) {
            return my_date($apps->updated_timestamp);
        });

        $table->filterColumn('updated_timestamp', function ($query, $keyword) {
            $query->whereRaw("DATE_FORMAT(FROM_UNIXTIME(updated_timestamp),' % d -%m -%Y % h:%i:%s') like ?", ["%$keyword%"]);
        });


        /*$table->filterColumn('author_name', function ($query, $keyword) {
          $query->whereRaw("stories.author_name like ?", ["%$keyword%"]);
        });*/

        $table->filterColumn('email', function ($query, $keyword) {
            $query->whereRaw("users.email like ?", ["%$keyword%"]);
        });

        $table->filterColumn('views', function ($query, $keyword) {
            $query->whereRaw("stories.views = $keyword");
        });

        $table->filterColumn('average_rate', function ($query, $keyword) {
            $query->whereRaw("story_ratings.average_rate = $keyword");
        });


        if (isset($this->data['rank_order']) && !empty($this->data['rank_order'])) {
            $table->order(function ($query) {
                $query->orderBy('story_ratings.average_rate', $this->data['rank_order']);
            });
        }

        if (isset($this->data['date_order']) && !empty($this->data['date_order'])) {

            $table->order(function ($query) {
                $query->orderBy('story_id', $this->data['date_order']);
            });
        }


// "rank_order":"desc","date_order":"asc"}


//If custom Filter Added.
        $this->setAdvanceFilter($request, $table);


        $table->rawColumns(['story_title', 'action']);


//        $endtime = microtime(true);
//        $timediff = $endtime - $starttime;
//
//        return $timediff;


        return $table->make(true);
    }

    public function setAdvanceFilter($request, $table)
    {


        // Filters

        $customFilters = $request->get('filter');
        $customFilters = \GuzzleHttp\json_decode($customFilters, true);
        $customFilters = isset($customFilters) ? $customFilters[0] : '';
        $filter = $customFilters['search[filter][]'];
        $operator = $customFilters['search[operator][]'];
        $value = $customFilters['search[value][]'];


        $monthFilter = $customFilters['month[filter]'];
        $monthOperator = $customFilters['month[operator]'];
        $monthsValue = $customFilters['month[value]'];


        if ($monthOperator == 'c') {
            $table->whereRaw('MONTH(FROM_UNIXTIME(stories.created_timestamp)) = MONTH(NOW()) AND YEAR(FROM_UNIXTIME(stories.created_timestamp)) = YEAR(CURRENT_DATE())');
        } else if ($monthOperator == 'e') {

            $table->whereRaw('MONTH(FROM_UNIXTIME(stories.created_timestamp)) = ' . $monthsValue);
        } else if ($monthOperator == 'om') {

            $table->whereRaw('stories.created_timestamp < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL ' . $monthsValue . ' MONTH))');
        } else if ($monthOperator == 'od') {

            $table->whereRaw('stories.created_timestamp < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL ' . $monthsValue . ' DAY))');
        }

        //$table->whereRaw("DATE_FORMAT(FROM_UNIXTIME(created_timestamp),'%d-%m-%Y %h:%i:%s') $operator ?", ["$value"]);


        foreach ($filter as $k => $cf):
            if ($filter[$k] && $value[$k] && $operator[$k]) {

                if ($filter[$k] == 'Rank')
                    $filter[$k] = 'average_rate';

                elseif ($filter[$k] == 'Views')
                    $filter[$k] = 'views';

                $table->where($filter[$k], $operator[$k], "$value[$k]");
            }
        endforeach;

        return $table;
    }

    public function formOptions(Request $request)
    {
        sleep(0.5);

        if ($request->get == 'stories') {


            $data = [];
            // $Result = Story::select(['story_id as id', 'story_title as text']);

            $Result = Story::whereNull("stories.deleted_at")->select(
                ['stories.story_id as id', 'story_title as text', 'average_rate', 'stories.theme_id', 'stories.subject_id']
            )
                ->join('story_ratings', 'story_ratings.story_id', '=', 'stories.story_id', "LEFT");


            $Result->where('views', ">", 100);
            $Result->where('average_rate', ">=", 4);

            if ($request->category_id) {
                $Result->where('category_id', $request->category_id);
            }
            if ($request->sub_category_id) {
                $Result->where('sub_category_id', $request->sub_category_id);
            }

            $Result->where("stories.theme_id", "!=", "41");
            $Result->where("stories.subject_id", "!=", "164");
            $Result = $Result->get()->toArray();

            //  echo count($Result);

            $data['data'] = $Result;
            $code = 200;
            $success = 'get_record_successfully';
            return response()->json(compact('code', 'success', 'data'));
        }
    }

    public function rateStory(Request $request)
    {

        if (request()->ajax()) {

            $validationRules = [
                'rate' => 'required',
                'story' => 'required'
            ];

            $validation = Validator::make($request->all(), $validationRules);

            if ($validation->fails()) {
                return response()->json(['code' => 201, 'error' => 'commented_error', 'message' => $validation->errors()]);
            }

            try {

                $alreadyRated = 0;
                $RatedResult = Rater::first()->where(['user_id' => 1, 'story_id' => $request->story])->get()->toArray();

                if (isset($RatedResult[0]) && !empty($RatedResult[0])) {
                    $alreadyRated = isset($RatedResult[0]['rate']) && !empty($RatedResult[0]['rate']) ? $RatedResult[0]['rate'] : 0;
                    $alreadyRated = (int)$alreadyRated;
                }

                $userRate = Rater::updateOrCreate(
                    ['user_id' => 1, 'story_id' => $request->story],
                    ['rate' => $request->rate]);

                if ($userRate->rater_id) {

                    $storyRating = Rating::firstOrNew(array('story_id' => $request->story));


                    $storyRating->story_id = $request->story;

                    if ($request->rate == 1)
                        $storyRating->r1 = $storyRating->r1 + 1;
                    if ($request->rate == 2)
                        $storyRating->r2 = $storyRating->r2 + 1;
                    if ($request->rate == 3)
                        $storyRating->r3 = $storyRating->r3 + 1;
                    if ($request->rate == 4)
                        $storyRating->r4 = $storyRating->r4 + 1;
                    if ($request->rate == 5)
                        $storyRating->r5 = $storyRating->r5 + 1;


                    /*if ($alreadyRated == 1)
                        $storyRating->r1 = $storyRating->r1 - 1;
                    if ($alreadyRated == 2)
                        $storyRating->r2 = $storyRating->r2 - 1;
                    if ($alreadyRated == 3)
                        $storyRating->r3 = $storyRating->r3 - 1;
                    if ($alreadyRated == 4)
                        $storyRating->r4 = $storyRating->r4 - 1;
                    if ($alreadyRated == 5)
                        $storyRating->r5 = $storyRating->r5 - 1;*/


                    $storyRating->average_rate = $storyRating->average_rate =
                        (
                            (
                                ($storyRating->r1 * 1)
                                + ($storyRating->r2 * 2)
                                + ($storyRating->r3 * 3)
                                + ($storyRating->r4 * 4)
                                + ($storyRating->r5 * 5)
                            ) / ($storyRating->r1
                                + $storyRating->r2
                                + $storyRating->r3
                                + $storyRating->r4
                                + $storyRating->r5)
                        );

                    $storyRating->total_rate =
                        $storyRating->r1
                        + $storyRating->r2
                        + $storyRating->r3
                        + $storyRating->r4
                        + $storyRating->r5;

                    if ($storyRating->save()) {

                        $story = Story::find($request->story);
                        $story->is_rated = 1;
                        $story->save();


                        return response()->json(['code' => 200, 'success' => 'rated_successfully'], 200);
                    }


                } else {
                    return response()->json(['code' => 201, 'error' => '', 'message' => '']);
                }

            } catch (\Exception $e) {

                return response()->json(['code' => 201, 'error' => '', 'message' => $e->getMessage()]);

            }

        }
    }

    public function makeNovelToStory($id){
        $story = Story::withTrashed()->find($id);
        $this->pageData['MainHeading'] = "Change Novel to Story for ".$story->story_title;
        $this->pageData['PageTitle'] =  "Change Novel to Story";
        $this->pageData['SubNav'] =  "Change Novel to Story";
        $this->pageData['NavHeading'] =  "Change Novel to Story";


        $fmForm = new FmForm('ChangeNovelToStoryFrm', route('admin-stories-update-story-subject', $id), "post", ['class' => 'smart-form', 'novalidate' => 'novalidate'], false);

        $fmForm->jsValidation($this->jsValidation);

        $subjects = Subject::where('subject_id','<>','177')->orderBy("subject_title", "asc")->get()->toArray();
        $subjects = array_combine(array_column($subjects, 'subject_id'), array_column($subjects, 'subject_title'));

        $fmForm->saveText('Save');

        $fmForm->add(array(
            "col" => 12,
            "type" => "select",
            "options" => $subjects,
            "name" => "subject_id",
            "label" => "Select Subject for Story",
            "value" => "",
            "attr" => ['style' => 'width:100%', 'class' => 'select2']));

        return view('admin.make-novel-to-story')
           ->with([
            'pageData' => $this->pageData,
            'form' => $fmForm->getForm()
        ]);
    }
    public function updateStorySubject($id, Request $request){
        $story = Story::withTrashed()->find($id);
        $story->subject_id = $request->input('subject_id');
        $story->save();

        StorySubject::where('story_id',$id)->where('subject_id','177')->delete();
        StorySubject::firstOrCreate([
            'story_id' => $id,
            'subject_id' => $request->input('subject_id'),
            'created_timestamp' => time(),
            'update_by' => 'client'
        ]);

        $request->session()->flash('alert-success', 'Novel has been converted to story successfully!');
        return redirect()->route("admin-stories-list");
    }
    public function updateStoryDate() {
       
        $stories = Story::all();
        foreach ($stories as $story) {
            $timestamp = date('Y-m-d', $story->created_timestamp);
            if(strtotime($timestamp) < strtotime('2010-06-30')) {
                $new_date = strtotime('2010-06-30');
                $story->created_timestamp = intval($new_date);
                $story->update();
            }
        }
        return redirect('/story-admin/dashboard/');
    }

}
