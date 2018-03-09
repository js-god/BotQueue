<?php

namespace App;

use App\Enums\HostRequestStatusEnum;
use App\Events\UserCreated;
use App\Exceptions\HostAlreadyClaimed;
use App\ModelTraits\CreatesMyCluster;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * App\User
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @method static \Illuminate\Database\Query\Builder|\App\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereEmail($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User wherePassword($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereRememberToken($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUsername($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Bot[] $bots
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Cluster[] $clusters
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\File[] $files
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Client[] $clients
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Passport\Token[] $tokens
 * @property bool $is_admin
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereIsAdmin($value)
 */
class User extends Authenticatable
{
    use Notifiable;
    use HasApiTokens;
    use CreatesMyCluster;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username', 'email', 'password',
    ];

    protected $attributes = [
        'is_admin' => false,
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $dispatchesEvents = [
        'created' => UserCreated::class,
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    public function promoteToAdmin()
    {
        $this->is_admin = true;
        $this->save();
    }

    /**
     * @param HostRequest $request
     * @param $name
     * @throws HostAlreadyClaimed
     */
    public function claim(HostRequest $request, $name)
    {
        try {
            HostRequest::query()
                ->whereKey($request->getKey())
                ->where('status', HostRequestStatusEnum::REQUESTED)
                ->whereNull('claimer_id')
                ->update([
                    'claimer_id' => $this->id,
                    'status' => HostRequestStatusEnum::CLAIMED,
                    'name' => $name,
                ]);

            $request->refresh();

            if($request->claimer_id != $this->id)
                throw new HostAlreadyClaimed("This host request has already been claimed by someone else");
        } catch (\Exception|\Throwable $e) {
            throw new HostAlreadyClaimed("Unexpected exception while trying to grab host request");
        }
    }

    public function bots()
    {
        return $this->hasMany(Bot::class, 'creator_id');
    }

    public function clusters()
    {
        return $this->hasMany(Cluster::class, 'creator_id');
    }

    public function files()
    {
        return $this->hasMany(File::class, 'uploader_id');
    }
}
