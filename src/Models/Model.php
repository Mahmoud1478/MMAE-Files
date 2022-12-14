<?php

namespace MMAE\Files\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MMAE\Files\Enum\AttributeEnum;
use MMAE\Files\Enum\FolderEnum;
use MMAE\Files\Enum\PropertyEnum;
use MMAE\Files\Events\ModelHasFileDeleted;
use MMAE\Files\Events\ModelHasFilesCreating;
use MMAE\Files\Events\ModelHasFilesSaved;
use MMAE\Files\Events\ModelHasFilesUpdating;
use MMAE\Files\Service\FileSystem;
use MMAE\Files\Service\Path;
use MMAE\Files\Service\Property;

 class Model extends Eloquent
{

    protected $dispatchesEvents = [
        'creating' => ModelHasFilesCreating::class,
        'saved' => ModelHasFilesSaved::class,
        'deleted' => ModelHasFileDeleted::class,
        'updating' => ModelHasFilesUpdating::class
    ];
    public function scopeWithFileUrl(Builder $query)
    {
        $attr = prop(AttributeEnum::File)();
        $query->addSelect(DB::raw('concat("' . Path::getFileUrl(static::class) . '","/",' . $attr . ') as ' . $attr . '_url'));
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model')
            ->selectRaw('*,concat("' . Path::getFileUrl($this::class,prop(FolderEnum::Media)) . '","/",folder,"/",name) as url')
            ->when(Property::get(static::class,prop(PropertyEnum::MediaThumbActivate), false) &&
                Property::get(static::class,prop(PropertyEnum::MediaIsImage),  false),
                function (Builder $q) {
                    $q->selectRaw('concat("' . Path::getFileUrl(static::class,prop(FolderEnum::Media)) . '","/",folder,"/","thumb_",name) as thumb');
                });
    }

    public function deleteFiles(?array $ids , ?array $objects = null): static
    {
        if(Property::get(static::class,prop(PropertyEnum::MediaActivate),false)){
            if ($ids){$folders = $this->files()->pluck('folder');}else{$ids = [];$folders = $objects??[];}
            foreach ($folders as $folder){
                FileSystem::rmdir(Path::getFileDiskPath($this::class,prop(FolderEnum::Media).DIRECTORY_SEPARATOR.$folder));
            }
            if ($ids) $this->files()->whereIn('id' , $ids)->delete();
        }
        return $this;
    }
     public static function getClassFolder(): string
     {
         $array = explode(DIRECTORY_SEPARATOR, static::class);
         return strtolower(Str::plural(end($array)));
     }

     public static function getFileAttr(): string
     {
         return 'img';
     }

     public static function getMediaRequestField(): string
     {
         return 'files';
     }
     public static function registerMediaColumns(int $index , UploadedFile $file): array
     {
         return [];

     }

}
