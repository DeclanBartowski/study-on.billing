<?php
namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class RegistrationDTO
{
    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Invalid email format.')]
    public string $email;

    /**
     * @Serializer\Type("string")
     * @Assert\NotBlank(message="Password is required.")
     * @Assert\Length(min=6, minMessage="Password must be at least 6 characters long.")
     */
    #[Serializer\Type('string')]
    #[Assert\NotBlank(message: 'Password is required.')]
    #[Assert\Length(min: 6, minMessage: 'Password must be at least 6 characters long.')]
    public string $password;
}
