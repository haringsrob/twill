<?php

namespace A17\Twill\Models\Behaviors;

use Illuminate\Support\Str;

trait HasSlug
{
    private $nb_variation_slug = 3;

    protected static function bootHasSlug()
    {
        static::created(function ($model) {
            $model->setSlugs();
        });

        static::updated(function ($model) {
            $model->setSlugs();
        });

        static::restored(function ($model) {
            $model->setSlugs($restoring = true);
        });
    }

    /**
     * Defines the one-to-many relationship for slug objects.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function slugs()
    {
        return $this->hasMany($this->getSlugModelClass());
    }

    /**
     * Returns an instance of the slug class for this model.
     *
     * @return object
     */
    public function getSlugClass()
    {
        return new $this->getSlugModelClass();
    }

    /**
     * Returns the fully qualified slug class name for this model.
     *
     * @return string|null
     */
    public function getSlugModelClass()
    {
        $slug = $this->getNamespace() . "\Slugs\\" . $this->getSlugClassName();

        if (@class_exists($slug)) {
            return $slug;
        }

        return $this->getCapsuleSlugClass(class_basename($this));
    }

    protected function getSlugClassName()
    {
        return class_basename($this) . "Slug";
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $slug
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSlug($query, $slug)
    {
        return $query->whereHas('slugs', function ($query) use ($slug) {
            $query->whereSlug($slug);
            $query->whereActive(true);
            $query->whereLocale(app()->getLocale());
        })->with(['slugs']);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $slug
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForInactiveSlug($query, $slug)
    {
        return $query->whereHas('slugs', function ($query) use ($slug) {
            $query->whereSlug($slug);
            $query->whereLocale(app()->getLocale());
        })->with(['slugs']);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $slug
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForFallbackLocaleSlug($query, $slug)
    {
        return $query->whereHas('slugs', function ($query) use ($slug) {
            $query->whereSlug($slug);
            $query->whereActive(true);
            $query->whereLocale(config('translatable.fallback_locale'));
        })->with(['slugs']);
    }

    /**
     * @param bool $restoring
     * @return void
     */
    public function setSlugs($restoring = false)
    {
        foreach ($this->getSlugParams() as $slugParams) {
            $this->updateOrNewSlug($slugParams, $restoring);
        }
    }

    /**
     * @param array $slugParams
     * @param bool $restoring
     * @return void
     */
    public function updateOrNewSlug($slugParams, $restoring = false)
    {
        if (in_array($slugParams['locale'], config('twill.slug_utf8_languages', []))) {
            $slugParams['slug'] = $this->getUtf8Slug($slugParams['slug']);
        } else {
            $slugParams['slug'] = Str::slug($slugParams['slug']);
        }

        //active old slug if already existing or create a new one
        if ((($oldSlug = $this->getExistingSlug($slugParams)) != null)
            && ($restoring ? $slugParams['slug'] === $this->suffixSlugIfExisting($slugParams) : true)) {
            if (!$oldSlug->active && ($slugParams['active'] ?? false)) {
                $this->slugs()->where('id', $oldSlug->id)->update(['active' => 1]);
                $this->disableLocaleSlugs($oldSlug->locale, $oldSlug->id);
            }
        } else {

            $this->addOneSlug($slugParams);
        }
    }

    /**
     * @param array $slugParams
     * @return object|null
     */
    public function getExistingSlug($slugParams)
    {
        unset($slugParams['active']);

        $query = $this->slugs();

        foreach ($slugParams as $key => $value) {
            //check variations of the slug
            if ($key == 'slug') {
                $query->where(function ($query) use ($value) {
                    $query->orWhere('slug', $value);
                    $query->orWhere('slug', $value . '-' . $this->getSuffixSlug());
                    for ($i = 2; $i <= $this->nb_variation_slug; $i++) {
                        $query->orWhere('slug', $value . '-' . $i);
                    }
                });
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    protected function addOneSlug($slugParams)
    {
        $datas = [];
        foreach ($slugParams as $key => $value) {
            $datas[$key] = $value;
        }

        $datas['slug'] = $this->suffixSlugIfExisting($slugParams);

        $datas[$this->getForeignKey()] = $this->id;

        $id = $this->slugs()->insertGetId($datas);

        $this->disableLocaleSlugs($slugParams['locale'], $id);
    }

    /**
     * @param string $locale
     * @param int $except_slug_id
     * @return void
     */
    public function disableLocaleSlugs($locale, $except_slug_id = 0)
    {
        $this->slugs()
            ->where($this->getForeignKey(), $this->id)
            ->where('id', '<>', $except_slug_id)
            ->where('locale', $locale)
            ->update(['active' => 0]);
    }

    private function suffixSlugIfExisting($slugParams)
    {
        $slugBackup = $slugParams['slug'];

        unset($slugParams['active']);

        for ($i = 2; $i <= $this->nb_variation_slug + 1; $i++) {
            $qCheck = $this->slugs();
            $qCheck->whereNull($this->getDeletedAtColumn());
            foreach ($slugParams as $key => $value) {
                $qCheck->where($key, '=', $value);
            }

            if ($qCheck->first() == null) {
                break;
            }

            if (!empty($slugParams['slug'])) {
                $slugParams['slug'] = $slugBackup . (($i > $this->nb_variation_slug) ? "-" . $this->getSuffixSlug() : "-{$i}");
            }
        }

        return $slugParams['slug'];
    }

    /**
     * Returns the active slug object for this model.
     *
     * @param string|null $locale Locale of the slug if your site has multiple languages.
     * @return object|null
     */
    public function getActiveSlug($locale = null)
    {
        return $this->slugs->first(function ($slug) use ($locale) {
            return ($slug->locale === ($locale ?? app()->getLocale())) && $slug->active;
        }) ?? null;
    }

    /**
     * Returns the fallback active slug object for this model.
     *
     * @return object|null
     */
    public function getFallbackActiveSlug()
    {
        return $this->slugs->first(function ($slug) {
            return $slug->locale === config('translatable.fallback_locale') && $slug->active;
        }) ?? null;
    }

    /**
     * Returns the active slug string for this model.
     *
     * @param string|null $locale Locale of the slug if your site has multiple languages.
     * @return string
     */
    public function getSlug($locale = null)
    {
        if (($slug = $this->getActiveSlug($locale)) != null) {
            return $slug->slug;
        }

        if (config('translatable.use_property_fallback', false) && (($slug = $this->getFallbackActiveSlug()) != null)) {
            return $slug->slug;
        }

        return "";
    }

    /**
     * @return string
     */
    public function getSlugAttribute()
    {
        return $this->getSlug();
    }

    /**
     * @param string|null $locale
     * @return array|null
     */
    public function getSlugParams($locale = null)
    {
        if (count(getLocales()) === 1 || !isset($this->translations)) {
            $slugParams = $this->getSingleSlugParams($locale);
            if ($slugParams != null && !empty($slugParams)) {
                return $slugParams;
            }
        }

        $slugParams = [];
        foreach ($this->translations as $translation) {
            if ($translation->locale == $locale || $locale == null) {
                $attributes = $this->slugAttributes;

                $slugAttribute = array_shift($attributes);

                $slugDependenciesAttributes = [];
                foreach ($attributes as $attribute) {
                    if (!isset($this->$attribute)) {
                        throw new \Exception("You must define the field {$attribute} in your model");
                    }

                    $slugDependenciesAttributes[$attribute] = $this->$attribute;
                }

                if (!isset($translation->$slugAttribute) && !isset($this->$slugAttribute)) {
                    throw new \Exception("You must define the field {$slugAttribute} in your model");
                }

                $slugParam = [
                    'active' => $translation->active,
                    'slug' => $translation->$slugAttribute ?? $this->$slugAttribute,
                    'locale' => $translation->locale,
                ] + $slugDependenciesAttributes;

                if ($locale != null) {
                    return $slugParam;
                }

                $slugParams[] = $slugParam;
            }
        }

        return $locale == null ? $slugParams : null;
    }

    /**
     * @param string|null $locale
     * @return array|null
     */
    public function getSingleSlugParams($locale = null)
    {
        $slugParams = [];
        foreach (getLocales() as $appLocale) {
            if ($appLocale == $locale || $locale == null) {
                $attributes = $this->slugAttributes;
                $slugAttribute = array_shift($attributes);
                $slugDependenciesAttributes = [];
                foreach ($attributes as $attribute) {
                    if (!isset($this->$attribute)) {
                        throw new \Exception("You must define the field {$attribute} in your model");
                    }

                    $slugDependenciesAttributes[$attribute] = $this->$attribute;
                }

                if (!isset($this->$slugAttribute)) {
                    throw new \Exception("You must define the field {$slugAttribute} in your model");
                }

                $slugParam = [
                    'active' => 1,
                    'slug' => $this->$slugAttribute,
                    'locale' => $appLocale,
                ] + $slugDependenciesAttributes;

                if ($locale != null) {
                    return $slugParam;
                }

                $slugParams[] = $slugParam;
            }
        }

        return $locale == null ? $slugParams : null;
    }

    /**
     * Returns the database table name for this model's slugs.
     *
     * @return string
     */
    public function getSlugsTable()
    {
        return $this->slugs()->getRelated()->getTable();
    }

    /**
     * Returns the database foreign key column name for this model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return Str::snake(class_basename(get_class($this))) . "_id";
    }

    protected function getSuffixSlug()
    {
        return $this->id;
    }

    /**
     * Generate a URL friendly slug from a UTF-8 string.
     *
     * @param string $str
     * @param array $options
     * @return string
     */
    public function getUtf8Slug($str, $options = [])
    {
        // Make sure string is in UTF-8 and strip invalid UTF-8 characters
        $str = mb_convert_encoding((string) $str, 'UTF-8', mb_list_encodings());

        $defaults = array(
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => array(),
            'transliterate' => true,
        );

        // Merge options
        $options = array_merge($defaults, $options);

        $char_map = array(
            // Latin
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
            'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
            'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
            'ÿ' => 'y',

            // Latin symbols
            '©' => '(c)',

            // Greek
            'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
            'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
            'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
            'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
            'Ϋ' => 'Y',
            'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
            'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
            'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
            'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
            'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',

            // Turkish
            'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
            'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g',

            // Russian
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
            'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
            'Я' => 'Ya',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
            'я' => 'ya',

            // Ukrainian
            'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
            'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',

            // Kazakh
            'Ә' => 'A', 'Ғ' => 'G', 'Қ' => 'Q', 'Ң' => 'N', 'Ө' => 'O', 'Ұ' => 'U',
            'ә' => 'a', 'ғ' => 'g', 'қ' => 'q', 'ң' => 'n', 'ө' => 'o', 'ұ' => 'u',

            // Czech
            'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
            'Ž' => 'Z',
            'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
            'ž' => 'z',

            // Polish
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z',
            'Ż' => 'Z',
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
            'ż' => 'z',

            // Latvian
            'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N',
            'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
            'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
            'š' => 's', 'ū' => 'u', 'ž' => 'z',

            // Romanian
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ț' => 'T',
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
        );

        // Make custom replacements
        $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);

        // Transliterate characters to ASCII
        if ($options['transliterate']) {
            $str = str_replace(array_keys($char_map), $char_map, $str);
        }

        // Replace non-alphanumeric characters with our delimiter
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);

        // Remove duplicate delimiters
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);

        // Truncate slug to max. characters
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');

        // Remove delimiter from ends
        $str = trim($str, $options['delimiter']);

        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }

    /**
     * Generate a URL friendly slug from a given string.
     *
     * @param string $string
     * @return string
     */
    public function urlSlugShorter($string)
    {
        return strtolower(trim(preg_replace('~[^0-9a-z]+~i', '-', html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities($string, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8')), '-'));
    }

    /**
     * Returns the fully qualified namespace for this model.
     *
     * @return string
     */
    public function getNamespace()
    {
        $pos = mb_strrpos(self::class, '\\');

        if ($pos === false) {
            return self::class;
        }

        return Str::substr(self::class, 0, $pos);
    }
}
