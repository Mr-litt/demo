# 定义命名空间
namespace java Services.UserService
namespace php Services.UserService
namespace py Services.UserService

# 定义枚举类型
enum ErrCodeEnum {
    SERVER_ERROR = 10001,
    PARAM_ERROR = 20001,
}

# 定义结构体
struct UserListReq {
    1: required list<i32> uidList;
}

struct UserInfo {
    1: required i32 uid,
    2: required string name,
    3: required i8 sex,
    4: required i16 age,
    5: optional string nick = '',
}

struct UserListResp {
    1: required list<UserInfo> lists;
}

# 定义异常
exception ApiErrorException {
    1: ErrCodeEnum errCode;
    2: string errMsg;
}

# 定义服务
service UserService{

    # 获取用户列表
    UserListResp userList(1: UserListReq req) throws (1: ApiErrorException e)

}


