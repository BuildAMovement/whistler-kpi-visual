<?php
namespace Component;

/**
 *
 * @method static \Component\User getInstance()
 *        
 * @property int $id
 *
 */
class User
{
    use \Ufw\Singleton;

    protected $id;

    /**
     *
     * @var \Model\Record\User
     */
    protected $user;

    public function __get($name)
    {
        return $this->getUser()->$name;
    }

    public function isLoggedOn()
    {
        return !!$this->getId();
    }

    public function isValid($id)
    {
        return !!$this->getUser($id);
    }

    /**
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @param int $id
     * @return \Component\User
     */
    public function setId($id)
    {
        $this->id = $id;
        if ($this->id) {
            if (isset($this->user) && ($this->getUser()->id != $this->id)) {
                $this->setUser(null);
            }
        } else {
            $this->setUser(null);
        }
        return $this;
    }

    /**
     *
     * @param int $id
     * @return \Model\Record\User
     */
    public function getUser($id = null)
    {
        if (!isset($this->user)) {
            if (!$id) {
                $id = $this->getId();
            }
            if ($id) {
                $this->user = (new \Model\User())->one($id);
            }
        }
        return $this->user;
    }

    /**
     *
     * @param \Model\Record\User $user
     * @return \Component\User
     */
    public function setUser(\Model\Record\User $user = null)
    {
        $this->user = $user;
        return $this;
    }

    /**
     *
     * @see django.contrib.auth.backends.has_perm()
     *
     * @param string $perm
     * @param \Model\Record\Base $obj
     * @return boolean
     */
    public function hasPerm($perm, \Model\Record\Base $obj)
    {
        // if ($this->is_active && $this->is_superuser) {
        // return true;
        // }
        return $this->_userHasPerm($perm, $obj);
    }

    /**
     *
     * @see django.contrib.auth.backends.has_perm()
     * @see guardian.backends.has_perm()
     *
     * @param string $perm
     * @param \Model\Record\Base $obj
     * @return boolean
     */
    protected function _userHasPerm($perm, \Model\Record\Base $obj)
    {
        /*
         * :: guardian implementation of has_perm ::
         *
         * def has_perm(self, user_obj, perm, obj=None):
         * """
         * Returns ``True`` if given ``user_obj`` has ``perm`` for ``obj``. If no
         * ``obj`` is given, ``False`` is returned.
         *
         * .. note::
         *
         * Remember, that if user is not *active*, all checks would return
         * ``False``.
         *
         * Main difference between Django's ``ModelBackend`` is that we can pass
         * ``obj`` instance here and ``perm`` doesn't have to contain
         * ``app_label`` as it can be retrieved from given ``obj``.
         *
         * *Inactive user support**
         *
         * If user is authenticated but inactive at the same time, all checks
         * always returns ``False``.
         * """
         *
         * # check if user_obj and object are supported
         * support, user_obj = check_support(user_obj, obj)
         * if not support:
         * return False
         *
         * if '.' in perm:
         * app_label, perm = perm.split('.')
         * if app_label != obj._meta.app_label:
         * # Check the content_type app_label when permission
         * # and obj app labels don't match.
         * ctype = get_content_type(obj)
         * if app_label != ctype.app_label:
         * raise WrongAppError("Passed perm has app label of '%s' while "
         * "given obj has app label '%s' and given obj"
         * "content_type has app label '%s'" %
         * (app_label, obj._meta.app_label, ctype.app_label))
         *
         * check = ObjectPermissionChecker(user_obj)
         * return check.has_perm(perm, obj)
         */
        if (strpos($perm, '.')) {
            list ($appLabel, $perm) = explode('.', $perm);
            
            $dbc = \Db\Db::instance();
            $query = $dbc->simple_select_query('django_content_type', [
                'app_label' => $appLabel,
                'model' => $obj->getDjangoContentTypeModel()
            ]);
            list ($contentTypeId) = $dbc->fetch_all($query, null, 'local', null, 'id');
            
            $query = $dbc->simple_select_query('auth_permission', [
                'content_type_id' => $contentTypeId,
                'codename' => $perm
            ]);
            list ($permissionId) = $dbc->fetch_all($query, null, 'local', null, 'id');
            
            $query = $dbc->simple_select_query('guardian_userobjectpermission', [
                'object_pk' => $obj->{$obj->getPrimaryKeyField()},
                'user_id' => $this->id,
                'permission_id' => $permissionId,
                'content_type_id' => $contentTypeId
            ]);
            list ($passed) = $dbc->fetch_all($query, null, 'local', null, 'id');
            
            return !!$passed;
        } else {
            // ?!
            return false;
        }
    }
}